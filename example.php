<?php

define("ROOT_DIR", __DIR__);
require_once './lib/postfile.class.php';
require_once './lib/vkapi.class.php';
require_once './foundation.class.php';

$group_id = 'id to vk group/public/user';
$vk_app_id = 'vk api application';
$vk_app_secret = 'vk api app secret';
$vk_token = 'vk api token'; //for example - use this tools to get access_token: https://github.com/gettocat/vktoken
$post_text = '<text of the post>';
$image_web_path = '<path to image from web>';

$access = new vkAcess($group_id, $vk_app_id, $vk_app_secret);
$access->setToken($vk_token);

$text = html_entity_decode(strip_tags(br2nl($post_text)), ENT_QUOTES | ENT_HTML5);

$vkPost = new vkPost($access);
$vkPost->setText($text);
$vkPost->setDate(time() + 300); //publish in next 5 minuts.

$img = $image_web_path;
$p = explode("/", $img);
$name = $p[count($p) - 1];
$ext = explode(".", $name);
$ext = $ext[count($ext) - 1];
$path = str_replace($name, "", $img);

//simple mime-type.
$mine = '';
if ($ext == 'jpg')
    $mine = 'image/jpeg';
else
    $mime = 'image/' . $ext;

$vkPost->addAttach(new vkAttach($access, vkAttach::ATTACH_PIC, array(
    'file' => ROOT_DIR . $path,
    'mime' => $mine,
    'name' => $name,
)));

$vkPost->addAttach(new vkAttach($access, vkAttach::ATTACH_LINK, array(
    'link' => "http://endfor.ru/tools"
)));

$res = $vkPost->publicate();

if ($res['id']) {
    //save vk_post_id as {$res['id']}
    echo "http://vk.com/wall" . $group_id . "_{$res['id']} see result.";
}
