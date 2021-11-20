<?php
include_once __DIR__.'/Router.php';

LiquidMS\Router::notFound(function($path){
      http_response_code(404);
      return "Unknown Action\n";
      });

LiquidMS\Router::invalidPage(function(){
      return "Unknown Action\n";
      });


/* GET API */

/*
LiquidMS\Router::get('/', function(){
      return "Unknown Action\n";
      });
*/

LiquidMS\Router::get('/rooms', function(){
      // The rooms Universe(0) and World(1) are technical and should always
      // be added with automatically generated MOTDs to indicate function
      //
      // Since network adresses too small to append to name (blame bitmap font),
      // the name of the fetch server shall be added as "@[address]" into
      // the first line of the MOTD.
      // Example: see dummy response

      // This is a demo mirror. Put DB queries here.
      $motd = yaml_parse_file("config.yaml.example")["motd"]; // Local var kludge
      return <<<END
0
Universe
Powered by liquidMS

This room queries all available rooms, internal and remote.

=MOTD=

${motd}


1
World
Powered by liquidMS

This room queries all available rooms internal to the node.

=MOTD=

${motd}


42
Dummy Room
Powered by liquidMS

You cannot do anything in this, it
merely serves to test the liquidMS API.


99
Dummy Room@
@dummynet.local
Powered by liquidMS

You cannot do anything in this, it
merely serves to test the liquidMS API.



END;
      //return "Bananarama";
      });

LiquidMS\Router::get('/rooms/([0-9]*)', function($roomid){
      return <<<END
${roomid}
Dummy Room
This is a dummy response.

You cannot do anything in this, it
merely serves to test the liquidMS API.


END;
      });

LiquidMS\Router::get('/rooms/([0-9]*)/servers', function($roomid){
      return <<<END
${roomid}
127.0.0.1 5029 Dummy%20server v2.2.9

END;
      });

LiquidMS\Router::get('/servers', function(){
    // Server test kludge. The game seems to ping every listed server and
    // filter by response. Listing dummy servers is thus not possible.
   $maincontent = file_get_contents("https://mb.srb2.org/MS/0/servers");
   return <<<END
42
127.0.0.1 5029 Dummy%20server 2.2.9

${maincontent}

END;
      });

LiquidMS\Router::get('/versions/([0-9]*)', function($versionid){
      $versionstring =
      yaml_parse_file("config.yaml.example")["versions"][$versionid]; // Local var kludge
      return "${versionstring}\n";
      });


/* POST API */
LiquidMS\Router::post('/rooms/([0-9]*)/register', function(){
      // Register Server and put ID here.  ID format is not specified; Vanilla 
      // returns numbers, we will return a random base64 string for security.
      return "42";
      });

LiquidMS\Router::post('/servers/([0-9]*)/update', function(){
      // No Response body
      return;
      });

LiquidMS\Router::post('/servers/([0-9]*)/unlist', function(){
      // No Response body
      return;
      });
?>
