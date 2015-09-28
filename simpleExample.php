<?php

/**
 * SIMPLE EXAMPLE
 */
$access = new vkAcess($vk_group_id, $vk_app_id, $vk_app_secret);
$access->setToken($vk_app_secret);

$vkPost = new vkPost($access);
$vkPost->setText("test");
$vkPost->setDate(time() + 300); //publish in next 5 minuts. By default: now

/**
 * OTHER ATTACHES EXAMPLE
 */


/**
 * PHOTO
 */
$vkPost->addAttach(new vkAttach($access, vkAttach::ATTACH_PIC, array(
    'file' => ROOT_DIR . $path,
    'mime' => $mine,
    'name' => $name,
)));

/*
 * VIDEO (not tested)
 */
$vkPost->addAttach(new vkAttach($access, vkAttach::ATTACH_VIDEO, array(
    'file' => L_FULLPATH . "/../",
    'mime' => 'video/x-ms-wmv',
    'name' => '1.wmv',
    'title' => 'video title',
    'desc' => 'test desc',
    'category' => 'category'
)));

/**
 * LINK
 */
$vkPost->addAttach(new vkAttach($access, vkAttach::ATTACH_LINK, array(
    'link' => 'http://google.ru'
)));

/**
 * DOCUMENT
 */
$vkPost->addAttach(new vkAttach($access, vkAttach::ATTACH_DOC, array(
    'file' => L_FULLPATH . "/../",
    'mime' => 'image/gif',
    'name' => '1.gif',
    'title' => 'РіРёС„РєР°.'
)));


/**
 * AUDIO
 */
$vkPost->addAttach(new vkAttach($access, vkAttach::ATTACH_AUDIO, array(
    'file' => L_FULLPATH . "/../",
    'mime' => 'audio/mp3',
    'name' => '1.mp3',
        //'audio_title'=>'',
        //'audio_artist'=>'',
)));

/**
 * ATTACH_ID
 */
$vkPost->addAttach(new vkAttach($access, vkAttach::ATTACH_ID, array(
    'id' => 'audio1_1'
)));




