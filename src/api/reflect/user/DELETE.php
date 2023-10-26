<?php

    use \Reflect\Rules;
    use \Reflect\Endpoint;
    use \Reflect\Response;
    use function \Reflect\Call;
    use \Reflect\Request\Method;

    class DELETE_ReflectUser implements Endpoint {
        public function __construct() {
            Rules::GET([
                "id" => [
                    "required" => true,
                    "min"      => 1,
                    "max"      => 128
                ]
            ]);
        }

        public function main(): Response {
            // Soft-delete user by setting active to false
            $delete = Call("reflect/user?id={$_GET["id"]}", Method::PUT, [
                "active" => false
            ]);
            
            return $delete->ok
                ? new Response("OK")
                : new Response(["Failed to delete user", $delete], 500);
        }
    }