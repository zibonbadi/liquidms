<?php
$response = $this->sharedData()->get('response');

echo "# Error Code {$response["error"]}\n\n{$response["message"]}\n\n---\n{$response["query"]}";

?>
