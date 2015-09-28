<?php

Class PostFile {

    protected $pathToScript;
    protected $port = 80;
    protected $timeout = 30;
    protected $host;
    protected $fileName;
    protected $fileVar;
    protected $pathToFile;
    protected $boundary;
    protected $socket = null;
    protected $_fileBuf;
    protected $userAgent;
    protected $answer = null;
    protected $fileType;

    public function __construct($fileName, $pathToFile, $fileVar = 'file1', $fileType = 'image/jpg') {
        $this->boundary = md5(uniqid(time()));
        $this->fileName = $fileName;
        $this->fileVar = $fileVar;
        $this->pathToFile = $pathToFile;
        $this->fileType = $fileType;
        $this->userAgent = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.56 Safari/535.11";
    }

    public function setFileType($ua) {
        $this->fileType = $ua;
        return $this;
    }

    public function setUserAgent($ua) {
        $this->userAgent = $ua;
        return $this;
    }

    public function setHost($host) {
        $this->host = $host;
        return $this;
    }

    public function setPort($port) {
        $this->port = $port;
        return $this;
    }

    public function setPathToScript($pToS) {
        $this->pathToScript = $pToS;
        return $this;
    }

    public function connect() {
        $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        if (!$this->socket)
            throw new Exception($errstr, $errno);

        $this->prepareUpload();
        $this->lastCheck();
    }

    protected function lastCheck() {
        if (!$this->boundary)
            throw new Exception("boundary not found!");

        if (!$this->socket)
            throw new Exception("error. Server not avalaible.");

        if (!$this->boundary)
            throw new Exception("boundary not found!");
    }

    protected function prepareUpload() {
        $this->_fileBuf = "--$this->boundary\r\n" .
                "Content-Disposition: form-data; name=\"" . $this->fileVar . "\";" .
                " filename=\"" . $this->fileName . "\"\r\n" .
                "Content-Type: " . $this->fileType . "\r\n" .
                "Content-Transfer-Encoding: binary\r\n\r\n";
        $this->_fileBuf.= file_get_contents($this->pathToFile . DIRECTORY_SEPARATOR . $this->fileName);
        $this->_fileBuf.="\r\n";
    }

    public function request() {
        $var1 = "--$this->boundary\r\nContent-Disposition: form-data; name=\"name\"\r\n\r\n" . urlencode("John") . "\r\n"; //без них не работает, О_О
        $var2 = "--$this->boundary\r\nContent-Disposition: form-data;name=\"surname\"\r\n\r\n" . urlencode("Smith") . "\r\n";
        fwrite($this->socket, "POST " . $this->pathToScript . " HTTP/1.1\r\n");
//а также имя хоста
        fwrite($this->socket, "Host: " . $this->host . "\r\n");
//представимся оперой
        fwrite($this->socket, "User-agent: " . $this->userAgent . "\r\n");
        fwrite($this->socket, "Connection: close\r\n");
//теперь отправляем заголовки
//Content-type должен быть multipart/form-data, 
//также должен быть указан разделитель, 
//который мы сгенерировали выше
        fwrite($this->socket, "Content-Type: multipart/form-data; boundary=$this->boundary\r\n");
//размер передаваемых данных передаем в заголовке 
//Content-length
        fwrite($this->socket, "Content-length:" . (strlen($this->_fileBuf) + strlen($var1) +
                strlen($var2)) . "\r\n");
//типы принимаемых данных. */* 
//означает, что принимаем все типы данных
        fwrite($this->socket, "Accept:*/*\r\n");
        fwrite($this->socket, "\r\n");
//теперь передаем данные
//передаем файл
        fwrite($this->socket, "$this->_fileBuf");
        fwrite($this->socket, "$var1$var2");
//в конце разделитель
        fwrite($this->socket, "--$this->boundary--\r\n");
//и пустая строка
        fwrite($this->socket, "\r\n");
//теперь читаем и выводим ответ
        $this->readAnswer();
    }

    protected function readAnswer() {
        $answer = '';
        while (!feof($this->socket)) {
            $answer = fgets($this->socket, 4096);
            $this->answer .= $answer;
        }
    }

    public function isAnswer() {
        return!!$this->answer;
    }

    public function getAnswer() {
        return $this->answer;
    }

    public function __destruct() {
        //закрываем сокет
        fclose($this->socket);
    }

}