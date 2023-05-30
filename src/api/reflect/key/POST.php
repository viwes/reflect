<?php

    use \Reflect\Path;
    use \Reflect\Endpoint;
    use \Reflect\Response;
    use function \Reflect\Call;
    use \Reflect\Request\Method;
    use \Reflect\Request\Connection;
    use \Reflect\Database\AuthDB;

    require_once Path::reflect("src/request/Router.php");
    require_once Path::reflect("src/database/Auth.php");

    class POST_ReflectKey extends AuthDB implements Endpoint {
        const POST = [
            "id"      => [
                "required" => false,
                "type"     => "text",
                "min"      => 32,
                "max"      => 32
            ],
            "user"    => [
                "required" => true
            ],
            "expires" => [
                "required" => false,
                "type"     => "int",
                "max"      => PHP_INT_MAX
            ]
        ];

        public function __construct() {
            parent::__construct(Connection::INTERNAL);
        }

        // Derive key from a SHA256 hash of user id and current time if no custom key is provided
        private function derive_key(): string {
            return $_POST["id"] = substr(hash("sha256", implode("", [$_POST["user"], time()])), -32);
        }

        public function main(): Response {
            // Check that the user exists and is active
            $user = Call("reflect/user?id={$_POST["user"]}", Method::GET);
            if (!$user->ok) {
                return new Response(["Failed to create key", "No user with id '{$_POST["user"]}' found"], 404);
            }

            // Generate API key if not provided
            $_POST["id"] = !empty($_POST["id"]) ? $_POST["id"] : $this->derive_key();

            // Attempt to insert key
            $sql = "INSERT INTO api_keys (id, user, expires, created) VALUES (?, ?, ?, ?)";
            $res = $this->return_bool($sql, [
                $_POST["id"],
                // Set user id
                $_POST["user"],
                // Set expiry timestamp if defined
                !empty($_POST["expires"]) ? $_POST["expires"] : null,
                // Set created timestamp
                time()
            ]);

            return !empty($res) 
                ? new Response($_POST["id"])
                : new Response("Failed to create API key", 500);
        }
    }