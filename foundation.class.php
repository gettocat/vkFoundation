<?php

set_time_limit(0);

Class vkLog {

    protected static $log = array();

    public static function log($message) {
        self::$log[] = $message;
    }

    public static function getLog() {
        return self::$log;
    }

}

Class vkAcess {

    protected $app_id;
    protected $secret_key;
    protected $access_token;
    protected $group_id;

    /**
     *
     * @var vkapi
     */
    protected $vk;

    public function __construct($group_id, $app_id, $secret_key) {
        $this->group_id = $group_id;
        $this->app_id = $app_id;
        $this->secret_key = $secret_key;
        $this->vk = new vkapi($this->app_id, $this->secret_key);
    }

    public function setToken($at) {
        $this->access_token = $at;
    }

    public function getToken() {
        return $this->access_token;
    }

    /**
     * 
     * @return vkapi
     */
    public function getApi() {
        return $this->vk;
    }

    public function getGroupId() {
        return $this->group_id;
    }

}

Class vkAttach {

    const ATTACH_ID = 0;
    const ATTACH_PIC = 1;
    const ATTACH_VIDEO = 2;
    const ATTACH_DOC = 3;
    const ATTACH_AUDIO = 4;
    const ATTACH_AUDIO_NAME = 5;
    const ATTACH_LINK = 6;

    /**
     *
     * @var vkAcess
     */
    protected $access;
    protected $type;
    protected $link;
    protected $data;
    protected $attach_id;
    protected $file;
    protected $url;
    protected $audioname;
    protected $mime;
    protected $filename;
    protected $isValid = false;
    protected $uploaded_data = array();
    //video
    protected $category;
    protected $desc;
    protected $toWall;

    public function __construct(vkAcess $access, $type, $data) {
        $this->type = $type;
        $this->access = $access;
        $this->data = $data;
        if ($type == vkAttach::ATTACH_PIC || $type == vkAttach::ATTACH_VIDEO || $type == vkAttach::ATTACH_AUDIO || $type == vkAttach::ATTACH_DOC) {
            if ($data['file'])
                $this->file = $data['file'];

            if ($data['mime'])
                $this->mime = $data['mime'];

            if ($data['name'])
                $this->filename = $data['name'];


            if ($type == vkAttach::ATTACH_VIDEO) {
                if ($data['category'])
                    $this->category = $data['category'];

                if ($data['desc'])
                    $this->desc = $data['desc'];

                if ($data['toWall'])
                    $this->toWall = intval($data['toWall']);
            }
        } else if ($type == vkAttach::ATTACH_ID) {
            if ($data['id'])
                $this->attach_id = $data['id'];
        } else if ($type == vkAttach::ATTACH_AUDIO_NAME) {
            if ($data['name'])
                $this->audioname = $data['name'];
        } else if ($type == vkAttach::ATTACH_LINK) {
            if ($data['link'])
                $this->link = $data['link'];
        }


        if ($this->file || $this->url || $this->attach_id || $this->audioname || $this->link)
            $this->isValid = true;
        
    }

    public function isValid() {
        return $this->isValid;
    }

    public function upload() {
        $this->error = "";
        if ($this->type == vkAttach::ATTACH_PIC) {
            $resp = $this->access->getApi()->api('photos.getWallUploadServer', array('access_token' => $this->access->getToken(), 'group_id' => $this->access->getGroupId()), false);
            vkLog::log("uploading photo: photos.getWallUploadServer method");

            if ($resp['error']) {
                $this->error = $resp['error']['error_msg'] . ' in method photos.getWallUploadServer';
                vkLog::log("$this->error");
            }

            $imgPostUrl = $resp['response']['upload_url'];
            preg_match("/http:\/\/(.*)\.vk\.com(.*)/i", $imgPostUrl, $m);
            //$m[1]//server address
            //$m[2]//script path
            vkLog::log("uploading photo to server {$m[1]}.vk.com{$m[2]} file: $this->file".$this->filename);
            $file = new PostFile($this->filename, $this->file, 'photo', $this->mime);
            $file->setHost($m[1] . '.vk.com')->setPathToScript($m[2]);
            $file->connect();
            $file->request();
            if ($file->isAnswer()) {
                $serverAnswer = $file->getAnswer();
            } else {
                $this->error = 'Ошибка при создании соединения с сервером ' . $m[1] . '.vk.com';
                vkLog::log("error in connection to server {$m[1]}.vk.com");
            }

            list($headers, $content) = explode("\r\n\r\n", $serverAnswer);
            $PicSettings = json_decode($content, true);
            vkLog::log("upload response: " . print_r($serverAnswer, true));
            vkLog::log("upload response decoded: " . print_r($PicSettings, true));

            $pic = $this->access->getApi()->api('photos.saveWallPhoto', array('access_token' => $this->access->getToken(), 'server' => $PicSettings['server'], 'photo' => $PicSettings['photo'], 'hash' => $PicSettings['hash'], 'group_id' => $this->access->getGroupId()));
            
            if ($pic['error']) {
                $this->error = print_r($pic, true) . ' in method photos.saveWallPhoto';
                vkLog::log("photos.saveWallPhoto error $this->error");
            }

            $this->uploaded_data = $pic["response"][0]; //owner_id, id, photo_604
            $this->uploaded_data['type'] = 'photo';

            if (!$this->error)
                return true;
            return false;
        } else if ($this->type == vkAttach::ATTACH_VIDEO) {
            //get $aid if category is exist
            $a = array('name' => $this->filename, 'description' => $this->desc, 'wallpost' => $this->toWall, 'access_token' => $this->access->getToken(), 'group_id' => $this->access->getGroupId());

            if ($this->data['title'])
                $a['name'] = $this->data['title'];

            if ($this->category) {
                $aid = 0;
                $count = 0;
                $offset = 0;
                $items = array();
                $res = $this->access->getApi()->api('video.getAlbums', array('access_token' => $this->access->getToken(), 'owner_id' => -1 * $this->access->getGroupId()), false);

                $items = array_merge($items, $res['response']['items']);
                $count += count($items);
                if ($res['response']['count'] > $count) {
                    while ($res['response']['count'] > $count) {
                        $offset+=50;
                        $res = $this->access->getApi()->api('video.getAlbums', array('offset' => $offset, 'access_token' => $this->access->getToken(), 'owner_id' => -1 * $this->access->getGroupId()), false);
                        $items = array_merge($items, $resp['response']['items']);
                        $count += count($resp['response']['items']);
                    }
                }

                if (count($items))
                    foreach ($items as $item) {
                        if ($item['title'] == $this->category) {
                            $aid = $item['id'];
                            vkLog::log("finded album {$item['title']} - " . $aid);
                            break;
                        }
                    }

                if (!$aid) {
                    //create album
                    $res = $this->access->getApi()->api('video.addAlbum', array('title' => $this->category, 'access_token' => $this->access->getToken(), 'group_id' => $this->access->getGroupId()), false);
                    $aid = $res['response']['album_id'];
                    vkLog::log("create album " . $aid);
                }

                $a['album_id'] = $aid;
            }

            $resp = $this->access->getApi()->api('video.save', $a, false);
            vkLog::log("uploading photo: video.save method");

            if ($resp['error']) {
                $this->error = $resp['error']['error_msg'] . ' in method video.save';
                vkLog::log("$this->error");
            }

            $imgPostUrl = $resp['response']['upload_url'];
            $this->uploaded_data = $resp['response'];

            preg_match("/http:\/\/(.*)\.vk\.com(.*)/i", $imgPostUrl, $m);
            //$m[1]//server address
            //$m[2]//script path
            vkLog::log("uploading video to server {$m[1]}{$m[2]}");
            $file = new PostFile($this->filename, $this->file, 'video_file', $this->mime);
            $file->setHost($m[1] . '.vk.com')->setPathToScript($m[2]);
            $file->connect();
            $file->request();
            if ($file->isAnswer()) {
                $serverAnswer = $file->getAnswer();
            } else {
                $this->error = 'Ошибка при создании соединения с сервером ' . $m[1] . '.vk.com';
                vkLog::log("error in connection to server {$m[1]}.vk.com");
            }

            $PicSettings = json_decode($serverAnswer, true); 
            if ($PicSettings['error']) {
                $this->error = $PicSettings['error'] . ' in method video.save to server';
                vkLog::log("video.save error $this->error");
            }

            $this->uploaded_data['type'] = 'video';
            if (!$this->error)
                return true;
            return false;
        } else if ($this->type == vkAttach::ATTACH_DOC) {
            $resp = $this->access->getApi()->api('docs.getUploadServer', array('access_token' => $this->access->getToken()), false);
            vkLog::log("saving document with docs.getWallUploadServer");

            if ($resp['error']) {
                $this->error = $resp ['error']['error_msg'] . ' in method docs.getUploadServer';
                vkLog::log("$this->error");
            }

            $imgPostUrl = $resp['response']['upload_url'];
            preg_match("/http:\/\/(.*)\.vk\.com(.*)/i", $imgPostUrl, $m);
            //$m[1]//server address
            //$m[2]//script path
            vkLog::log("uploading doc to $imgPostUrl");
            $file = new PostFile($this->filename, $this->file, 'file', $this->mime);
            $file->setHost($m[1] . '.vk.com')->setPathToScript($m[2]);
            $file->connect();
            $file->request();
            if ($file->isAnswer()) {
                $serverAnswer = $file->getAnswer();
            } else {
                $this->error = 'Ошибка при создании соединения с сервером ' . $m[1] . '.vk.com';
                vkLog::log("error in connection to server {$m[1]}.vk.com");
            }

            $PicSettings = json_decode($serverAnswer, true); 
            $a = array('access_token' => $this->access->getToken(), 'file' => $PicSettings['file'], 'title' => $this->filename);

            if ($this->data['title'])
                $a['title'] = $this->data['title'];

            $pic = $this->access->getApi()->api('docs.save', $a);
            vkLog::log("saving doc by method docs.save file-{$PicSettings['file']}, {$this->filename}");
            if ($pic['error']) {
                $this->error = $resp['error']['error_msg'] . ' in method docs.save';
                vkLog::log($this->error);
            } else
                $this->uploaded_data = $pic['response'][0];

            $this->uploaded_data['type'] = 'doc';

            if (!$this->error)
                return true;
            return false;
        } else if ($this->type == vkAttach::ATTACH_AUDIO) {
            $resp = $this->access->getApi()->api('audio.getUploadServer', array('access_token' => $this->access->getToken()), false);
            vkLog::log("uploading photo: audio.getUploadServer method");

            if ($resp['error']) {
                $this->error = $resp['error']['error_msg'] . ' in method audio.getUploadServer';
                vkLog::log("$this->error");
            }


            $imgPostUrl = $resp['response']['upload_url'];
            preg_match("/http:\/\/(.*)\.vk\.com(.*)/i", $imgPostUrl, $m);
            //$m[1]//server address
            //$m[2]//script path
            vkLog::log("uploading photo to server {$m[1]}{$m[2]}");
            $file = new PostFile($this->filename, $this->file, 'file', $this->mime);
            $file->setHost($m[1] . '.vk.com')->setPathToScript($m[2]);
            $file->connect();
            $file->request();
            if ($file->isAnswer()) {
                $serverAnswer = $file->getAnswer();
            } else {
                $this->error = 'Ошибка при создании соединения с сервером ' . $m[1] . '.vk.com';
                vkLog::log("error in connection to server {$m[1]}.vk.com");
            }

            $PicSettings = json_decode($serverAnswer, true);
            $a = array('access_token' => $this->access->getToken(), 'server' => $PicSettings['server'], 'audio' => $PicSettings['audio'], 'hash' => $PicSettings['hash']);

            if ($this->data['audio_title'])
                $a['title'] = $this->data['audio_title'];

            if ($this->data['audio_artist'])
                $a['artist'] = $this->data['audio_artist'];

            $pic = $this->access->getApi()->api('audio.save', $a);
            
            if ($pic['error'])
                $this->error = $resp ['error']['error_msg'] . ' in method audio.save';
            vkLog::log("photos.saveWallPhoto error $this->error");

            $this->uploaded_data = $pic['response'];
            $this->uploaded_data['type'] = 'audio';
            if (!$this->error)
                return true;
            return false;
        } else if ($this->type == vkAttach::ATTACH_ID) {
            $this->uploaded_data['attach_id'] = $this->attach_id;
            return true;
        } else if ($this->type == vkAttach::ATTACH_LINK) {
            $this->uploaded_data['link'] = $this->link;
            return true;
        } else if ($this->type == vkAttach::ATTACH_AUDIO_NAME) {
            $list = $this->access->getApi()->api('audio.search', array('q' => $this->audioname, 'count' => 1, 'sort' => 2, 'access_token' => $this->access->getToken()));
            if ($list['response'][0] > 0) {
                $this->uploaded_data['attach_id'] = "audio" . $list['response'][1]['owner_id'] . "_" . $list['response'][1]['audio_id'];
                return true;
            }

            return false;
        }

        return false;
    }

    public function getId() {
        if ($this->type == vkAttach::ATTACH_PIC || $this->type == vkAttach::ATTACH_AUDIO || $this->type == vkAttach::ATTACH_DOC) {
            return $this->uploaded_data['type'] . $this->uploaded_data['owner_id'] . "_" . $this->uploaded_data['id'];
        } else if ($this->type == vkAttach::ATTACH_VIDEO) {
            return $this->uploaded_data['type'] . $this->uploaded_data['owner_id'] . "_" . $this->uploaded_data['video_id'];
        } else if ($this->type == vkAttach::ATTACH_AUDIO_NAME) {
            return $this->uploaded_data['attach_id'];
        } else if ($this->type == vkAttach::ATTACH_LINK) {
            return $this->link;
        } else if ($this->type == vkAttach::ATTACH_ID) {
            return $this->attach_id;
        }
    }

    public function getData() {
        return $this->uploaded_data;
    }

    public function getUniq() {
        return $this->type . "-" . $this->filename . "[{$this->file}]";
    }

}

Class vkPost {

    /**
     *
     * @var vkAcess

     */
    protected $access;
    protected $text;
    protected $date;

    /**
     *
     * @var vkAttach[]
     */
    protected $attaches = array();

    public function __construct(vkAcess $access) {
        $this->access = $access;
    }

    public function setText($text) {
        $this->text = $text;
        return $this;
    }

    public function setDate($date) {
        if ($date > time())
            $this->date = $date;
        return $this;
    }

    public function addAttach(vkAttach $att) {
        if (count($this->attaches) < 10 && $att->isValid())
            $this->attaches[] = $att;
        return $this;
    }

    public function publicate() {
        $attaches = array();
        if (count($this->attaches))
            foreach ($this->attaches as $a) {
                if ($a->upload()) {
                    $attaches[] = $a->getId();
                    sleep(1);
                } else {
                    vkLog::log("fail to upload attach " . $a->getUniq());
                }
            }

        $attaches = implode(",", $attaches);
        if (!trim($attaches) && !$this->text)
            return false;

        $resp = $this->access->getApi()->api('wall.post', array('publish_date' => $this->date, 'owner_id' => -1 * $this->access->getGroupId(), 'message' => $this->text, 'from_group' => 1, 'attachments' => $attaches, 'access_token' => $this->access->getToken()));
        if ($resp['response']['post_id'] || $resp['response']['processing']) {
            vkLog::log("post with attaches [$attaches] and text <" . strlen($this->text) . " symblos> publicated");
            return array('id' => $resp ['response']['post_id'], 'processing' => $resp['response']['processing']);
        } else {
            vkLog::log("post publicate error: {$resp['error']['error_msg']}");
            return false;
        }
    }

}

//$access = new vkAcess(G::$vk_id, VK_APPID, VK_SK);
//$access->setToken(VK_AT);

//$vkPost = new vkPost($access);
//$vkPost->setText("test");
/* photo
  $vkPost->addAttach(new vkAttach($access, vkAttach::ATTACH_PIC, array(
  'file' => L_FULLPATH . "/../",
  'mime' => 'image/jpeg',
  'name' => '1.jpg',
  ))); */

/* video
  $vkPost->addAttach(new vkAttach($access, vkAttach::ATTACH_VIDEO, array(
  'file' => L_FULLPATH . "/../",
  'mime' => 'video/x-ms-wmv',
  'name' => '1.wmv',
  'title'=>'Р¶РёРІРѕС‚РЅС‹Рµ',
  'desc'=>'test desc',
  'category'=>'РљР°С‚РµРіРѕСЂРёСЏ 1'
  )));
 */

/* attach_id
  $vkPost->addAttach(new vkAttach($access, vkAttach::ATTACH_ID, array(
  'id'=>'audio1_1'
  )));
 */

/* link
  $vkPost->addAttach(new vkAttach($access, vkAttach::ATTACH_LINK, array(
  'link'=>'http://google.ru'
  )));
 */

/* doc
$vkPost->addAttach(new vkAttach($access, vkAttach::ATTACH_DOC, array(
    'file' => L_FULLPATH . "/../",
    'mime' => 'image/gif',
    'name' => '1.gif',
    'title'=>'РіРёС„РєР°.'
)));*/

/*$vkPost->addAttach(new vkAttach($access, vkAttach::ATTACH_AUDIO, array(
    'file' => L_FULLPATH . "/../",
    'mime' => 'audio/mp3',
    'name' => '1.mp3',
    //'audio_title'=>'',
    //'audio_artist'=>'',
)));*/

//$vkPost->publicate();