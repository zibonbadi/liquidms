<?php
$config = [];
if( PHP_SAPI == "cli" ){
   $config = yaml_parse_file(__DIR__."/config.yaml", -1);

   // Merge all yaml configs together
   $tmp = [];
   foreach($config as $docname => $doc) {
      $tmp = array_merge_recursive($tmp, $doc);
   }
   $config = $tmp;
   unset($tmp);
}
?>
