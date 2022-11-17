<?php

    require_once Path::src("database/drivers/MariaDB.php");

    class AuthDB extends MariaDBDriver {
        // This is the default fallback key used when no key is provided
        // with the request (anonymous) and when a provided key lacks
        // access to a particular resource (forbidden).
        private static $key_default = "HTTP_ANYONE_KEY";

        public function __construct(private ConType $con) {
            parent::__construct($_ENV["db"]["authdb"]);

            // Get API key from GET parameter if not UNIX socket
            $this->key = $con === ConType::AF_UNIX ? "UNIX_SOCKET_KEY" : $this->get_api_key();
        }

        // Return bool user id is enabled
        private function user_active(string $user): bool {
            $sql = "SELECT NULL FROM api_users WHERE id = ? AND active = 1";
            return $this->return_bool($sql, $user);
        }

        // Validate API key from GET parameter
        private function get_api_key(): string {
            // No "key" parameter provided so use anonymous key
            if (empty($_SERVER["HTTP_AUTHORIZATION"])) {
                // Mock Authorization header
                $_SERVER["HTTP_AUTHORIZATION"] = "Bearer " . AuthDB::$key_default;
            }

            // Destruct Authorization header from <auth-scheme> <authorization-parameters>
            [$scheme, $key] = explode(" ", $_SERVER["HTTP_AUTHORIZATION"], 2);

            // Default to anonymos key if invalid scheme
            if ($scheme !== "Bearer") {
                return AuthDB::$key_default;
            }

            // Check that key exists, is active, and not expired
            $sql = "SELECT user FROM api_keys WHERE id = ? 
            AND active = 1 AND (? < expires)";

            $res = $this->return_array($sql, [
                $key,
                time()
            ]);
            
            // Return key from request or default to anonymous key if it's invalid
            return !empty($res) && $this->user_active($res[0]["user"]) ? $key : AuthDB::$key_default;
        }

        // Return bool endpoint enabled
        private function endpoint_active(string $endpoint): bool {
            $sql = "SELECT NULL FROM api_endpoints WHERE endpoint = ? AND active = 1 LIMIT 1";
            return $this->return_bool($sql, $endpoint);
        }

        // Return all available request methods to endpoint with key
        public function get_options(string $endpoint): array {
            $sql = "SELECT method FROM api_acl WHERE api_key = ? AND endpoint = ?";
            $res = $this->return_array($sql, [ $this->key, $endpoint ]);
            
            // Flatten array to only values of "method"
            return !empty($res) ? array_column($res, "method") : [];
        }

        // Check if API key is authorized to call endpoint using method
        public function check(string $endpoint, string $method): bool {
            // Ensure endpoint is enabled
            if (!$this->endpoint_active($endpoint)) {
                return false;
            }

            $test = $this->key;

            // Check if the API key has access to the requested endpoint and method
            $sql = "SELECT NULL FROM api_acl WHERE api_key = ? AND endpoint = ? AND method = ? LIMIT 1";
            return $this->return_bool($sql, [
                $this->key,
                $endpoint,
                $method
            ]);
        }
    }