<?php

    namespace Reflect\Socket;

    use const \Reflect\ENV;
    use \Reflect\Path;
    use \Reflect\Request\Router;
    use \Reflect\Request\Connection;

    require_once Path::reflect("src/request/Router.php");

    // Handle RESTful requests over AF_UNIX socket.
    class SocketServer {
        public function __construct(string $path) {
            // Can not access socket file
            if (!is_writable(dirname($path))) {
                throw new \Error("No permission: Cannot create socket file at '{$path}'");
            }

            // Delete existing socket file
            if (file_exists($path)) {
                unlink($path);
            }

            // Create and bind socket file
            $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
            socket_bind($this->socket, $path);

            // Set socket file permissions
            chmod($path, $_ENV[ENV]["socket_mode"]);

            $this->client = null;
        }

        // Convert message from client into simple PHP-styled presentation of HTTP request
        private function rx(string $payload) {
            // Expecting ["<endpoint>","<method>","<payload>"]
            [$uri, $_SERVER["REQUEST_METHOD"], $data] = json_decode($payload);

            // Request is malformed or connection interrupted, abort
            if (empty($uri)) {
                return false;
            }

            $uri = parse_url($uri);

            // Load request body fields into $_POST superglobal if set
            if (!empty($data)) {
                $_POST = json_decode($data, true);
            }

            $_SERVER['REQUEST_URI'] = isset($uri["path"]) ? "/" . $uri["path"] : "/"; // Set request path with leading slash
            isset($uri["query"]) ? parse_str($uri["query"], $_GET) : null; // Set request parameters
            
            // Initialize request router
            (new Router(Connection::AF_UNIX))->main();
        }

        private function txn() {
            $this->client = socket_accept($this->socket);
        }

        // Stop server
        public function stop() {
            socket_close($this->socket);
        }

        // Start server
        public function listen() {
            socket_listen($this->socket);

            $con = true;
            $data = "";

            // Create new socket for cross-communication
            while ($con === true) {
                $client = socket_accept($this->socket);

                // Bind handler for outgoing data
                $_ENV[ENV]["SOCKET_STDOUT"] = function (mixed $msg, int $code = 200) use (&$client) {
                    $tx = json_encode([$code, $msg]);
                    socket_write($client, $tx, strlen($tx));
                };

                // Parse incoming data
                $this->rx(socket_read($client, 2024));
            }

            socket_close($client);
        }
    }