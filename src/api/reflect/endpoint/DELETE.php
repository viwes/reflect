<?php

    use \Reflect\Path;
    use \Reflect\Endpoint;
    use \Reflect\Response;
    use function \Reflect\Call;
    use \Reflect\Request\Method;

    require_once Path::reflect("src/request/Router.php");

    class DELETE_ReflectEndpoint implements Endpoint {
        const GET = [
            "id" => [
                "required" => true,
                "min"      => 1,
                "max"      => 128
            ]
        ];

        public function __construct() {
            // ...
        }

        public function main(): Response {
            // Soft-delete endpoint by setting active to false
            $delete = Call("reflect/endpoint?id={$_GET["id"]}", Method::PUT, [
                "active" => false
            ]);
            return $delete->ok
                ? new Response("OK")
                : new Response(["Failed to delete key", $delete], 500);
        }
    }