<?php

    use \Reflect\Path;
    use \Reflect\Rules;
    use \Reflect\Endpoint;
    use \Reflect\Response;
    use function \Reflect\Call;
    use \Reflect\Request\Method;
    use \Reflect\Database\AuthDB;
    use \Reflect\Request\Connection;

    require_once Path::reflect("src/request/Router.php");
    require_once Path::reflect("src/database/Auth.php");

    class POST_ReflectEndpoint extends AuthDB implements Endpoint {
        public function __construct() {
            Rules::POST([
                "endpoint" => [
                    "required" => true,
                    "type"     => "text",
                    "min"      => 1,
                    "max"      => 128
                ],
                "active"   => [
                    "required" => false,
                    "type"     => "bool"
                ]
            ]);
            
            parent::__construct(Connection::INTERNAL);
        }

        public function main(): Response {
            // Attempt to INSERT new endpoint
            $sql = "INSERT INTO api_endpoints (endpoint, active) VALUES (?, ?)";
            $this->return_bool($sql, [$_POST["endpoint"], 1]);

            // Ensure the endpoint was successfully created
            $created = Call("reflect/endpoint?id=${endpoint}", Method::GET);
            return $created->ok
                ? new Response("OK")
                : new Response(["Failed to create endpoint", $created], 500);
        }
    }