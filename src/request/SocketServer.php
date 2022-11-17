<?php

    require_once "src/Init.php";
    require_once Path::src("request/Router.php");

    // Handle RESTful requests over AF_UNIX socket.
    class SocketServer {
        public function __construct() {
            $this->init();

            // Create and bind socket file
            $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
            socket_bind($this->socket, $_ENV["socket"]["listen"]);
        }

        // Initialize server
        private function init() {
            // Can not access socket file
            if (!is_writable($_ENV["socket"]["listen"])) {
                throw new Error("No permission: Cannot create .sock file at '{$_ENV["socket"]["listen"]}'");
            }

            // Delete existing socket file
            if (file_exists($_ENV["socket"]["listen"])) {
                unlink($_ENV["socket"]["listen"]);
            }
        }

        // Convert message from client into simple PHP-styled presentation of HTTP request
        private function parse(string $payload) {
            [$uri, $_SERVER["REQUEST_METHOD"], $data] = json_decode($payload);

            // Request is malformed or connection interrupted, abort
            if (empty($uri)) {
                return false;
            }

            $uri = parse_url($uri);
            $_POST = json_decode($data, true) ?? null;

            $_SERVER['REQUEST_URI'] = isset($uri["path"]) ? "/" . $uri["path"] : "/"; // Set request path with leading slash
            isset($uri["query"]) ? parse_str($uri["query"], $_GET) : null; // Set request parameters
            
            // Initialize request router
            (new Router(ConType::AF_UNIX))->main();
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

                //Bind handler for outgoing data
                $_ENV["SOCKET_STDOUT"] = function (string $msg, int $code = 200) use (&$client) {
                    $tx = json_encode([$code, $msg]);
                    socket_write($client, $tx, strlen($tx));
                };

                // Parse incoming data
                $this->parse(socket_read($client, 2024));
            }

            socket_close($client);
        }
    }