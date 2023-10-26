<?php

    use \Reflect\Path;
    use \Reflect\Rules;
    use \Reflect\Endpoint;
    use \Reflect\Response;
    use \Reflect\Request\Method;
    use \Reflect\Database\AuthDB;
    use \Reflect\Request\Connection;

    use \Reflect\Database\Endpoints\Model;

    require_once Path::reflect("src/database/Auth.php");
    require_once Path::reflect("src/database/model/Endpoints.php");

    class PUT_ReflectEndpoint extends AuthDB implements Endpoint {
        public function __construct() {
            Rules::GET([
                "id" => [
                    "required" => true,
                    "min"      => 1,
                    "max"      => 128
                ]
            ]);

            Rules::POST([
                "active"   => [
                    "required" => true,
                    "type"     => "bool"
                ]
            ]);

            parent::__construct(Connection::INTERNAL);
        }

        public function main(): Response {
            $update = $this->for(Model::TABLE)
                ->with(Model::values())
                ->where([
                    Model::ID->value => $_GET["id"]
                ])
                ->update([
                    Model::ACTIVE->value => $_POST["active"]
                ]);
            
            // Update the endpoint
            return $update ? new Response("OK") : new Response("Failed to update endpoint", 500);
        }
    }