<?php

define('BOT_TOKEN', '');
define('MAILGUN_TOKEN', '');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

require 'vendor/autoload.php';
use Mailgun\Mailgun;

/*$error_file = fopen('/tmp/bot_error.log','w');
fwrite($error_file, 'file is open');
fclose($error_file);*/

function error_log2 ($error_msg) {
    $error_file = fopen ('/tmp/bot_error.log', 'w');
    fwrite($error_file, $error_msg);
    fclose($error_file);
}

function sqlite_open($location) {
    $handle = new SQLite3 ($location);
    sqlite_query ($handle, 'CREATE table IF NOT EXIST tmp (id varchar(20) primary key, assunto varchar (50), local text)');
    sqlite_query ($handle, 'CREATE table IF NOT EXIST ti (id int auto_increment primary key, chat varchar (20), nome varchar (30) )');
    sqlite_query ($handle, 'CREATE table IF NOT EXIST chamados (id int auto_increment primary key, id_ti int, chat varchar (20), problema text )', FOREIGN KEY (id_ti) REFERENCES ti(id));
    return $handle;
}

function sqlite_query ($dbhandle, $query) {
    $array['dbhandle'] = $dbhandle;
    $array['query'] = $query;
    $result = $dbhandle->query($query);
    return $result;
}

function exec_curl_request($handle) {
  $response = curl_exec($handle);
  if ($response === false) {
    $errno = curl_errno($handle);
    $error = curl_error($handle);
    error_log2 ("Curl returned error $errno: $error\n");
    curl_close ($handle);
    return false;
  }
  $http_code = intval(curl_getinfo ($handle, CURLINFO_HTTP_CODE) );
  curl_close ($handle);
  if ($http_code >= 500) {
    sleep(10);
    return false;
  }
  else if ($http_code != 200) {
    $response = json_decode($response, true);
    error_log2 ("Request has failed with error {$response['error_code']}: {$response['description']}\n");
    if ($http_code == 401) {
      throw new Exception ('Invalid access token provided');
    }
    return false;
  }
  else {
    $response = json_decode($response, true);
    if (isset ($response['description']) ) {
      error_log2("Request was successfull: {$response['description']}\n");
    }
    $response = $response['result'];
  }
  return $response;
}

function apiSendMail ($text) {
  $client = new \Http\Adapter\Guzzle6\Client ();
  $mailgun = new \Mailgun\Mailgun (MAILGUN_TOKEN, $client);
  $domain = "sandbox57fbf15331ae4b308621142b6e48acb1.mailgun.org";
  $result = $mailgun->sendMessage($domain, array(
    'from'    => 'Mailgun Sandbox <postmaster@sandbox57fbf15331ae4b308621142b6e48acb1.mailgun.org>',
    'to'      => 'Thiago Abrantes <thiagosouza@iq.ufrj.br>',
    'subject' => '[BOT TELEGRAM]',
    'text'    => $text."¨ALGUMACOISAMUITOUNICABEMDIVERTIDA"
    ));
}

function apiRequestJson ($method, $parameters) {
  $parameters["method"] = $method;
  $handle = curl_init (API_URL);
  curl_setopt ($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt ($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt ($handle, CURLOPT_TIMEOUT, 60);
  curl_setopt ($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
  curl_setopt ($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
  return exec_curl_request($handle);
}

function processMessage ($message) {
  $chat_id = $message['chat']['id'];
  if (isset ($message['text']) ) {
    $text = $message['text'];
    error_log2 ($text);
    if (strpos ($text, "/singin") === 0) {
      if (substr ($text, 7, -0) === TI_TOKEN){
        $dbhandle = sqlite_open("/tmp/ladetecbot.db");
        $return = sqlite_query($dbhandle,
          "INSERT into ti values (,'".  $message['from']['first_name']." ".$message['from']['last_name']."','".$chat_id."');"
        );
      }
    }
    elseif (strpos ($text, "/take") === 0) {
      $dbhandle = sqlite_open("/tmp/ladetecbot.db");
      $return = sqlite_query($dbhandle, "SELECT id from chamados where chat='".$chat_id."';");
      $resx = $return->fetchArray();
      $return = sqlite_query($dbhandle,
        "UPDATE problema set id_ti=".$resx[0]." where id=".substr($text, 5, -0).";"
      );
    }
    elseif (strpos ($text, "/pass") === 0) {
      $text = substr($text, 5, -0);
      $pass = explode("-", $text);
      if (sizeof($pass) === 2) {
        $dbhandle = sqlite_open("/tmp/ladetecbot.db");
        $return = sqlite_query($dbhandle,"SELECT id from ti where name like '".$pass[0]."';");
        $resx = $return->fetchArray();
        $return = sqlite_query($dbhandle,
          "UPDATE chamados set id_ti=".$resx[0]." where id=".$pass[1].";"
        );
      }
      else{
        //erro_log2('more or less than 2 arguments!');
      }
    }
    elseif (strpos ($text, "/answer") === 0){
      $text = substr($text, 7, -0);
      $answer = explode("-", $text);
      if (sizeof($pass) === 2){
        $dbhandle = sqlite_open("/tmp/ladetecbot.db");
        $return = sqlite_query($dbhandle,"SELECT * from chamados where id=".$pass[1].";");
        $resx = $return->fetchArray();
        $return = sqlite_query($dbhandle,
          "UPDATE chamados set problema="$resx[3]."\nTI: ".$resx[0]." where id=".$pass[1].";"
        );
      }
      else{
        //erro_log2('more or less than 2 arguments!');
      }
    }
    elseif (strpos ($text, "/close") === 0) {
      $text = substr($text, 6, -0);
      $answer = explode("-", $text);
      if (sizeof($pass) === 2){
        $dbhandle = sqlite_open("/tmp/ladetecbot.db");
        $return = sqlite_query($dbhandle,"DELETE from chamados where id=".$pass[1].";");
      }
      else {
        //erro_log2('more or less than 2 arguments!');
      }
    }
    else {
      switch ($text) {
        case "/start":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual seu problema?', 'reply_markup' => array(
            'keyboard' => array(array('Banco de Dados'), array ('Equipamentos de Informática ou Programas'), array ('Outros')),
            'one_time_keyboard' => true,
            'resize_keyboard' => true)));
            $dbhandle = sqlite_open("/tmp/ladetecbot.db");
            $return = sqlite_query($dbhandle,
              "Delete from tmp where id='".$chat_id."';"
            );
            $return = sqlite_query($dbhandle,
              "Insert into tmp values (".$chat_id.", '', '');"
            );break;
       case "Banco de Dados":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Em qual banco esta tendo problemas?', 'reply_markup' => array(
            'keyboard' => array(array('Lims'), array ('Soluções'), array ('LBCD'), array ('Outro Banco de Dados')),
            'one_time_keyboard' => true,
            'resize_keyboard' => true)));break;
       case "Equipamentos de Informática ou Programas":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual tipo de equipamento?', 'reply_markup' => array(
            'keyboard' => array(array('Computador ou Periférico'), array('Impressora'),array('Controle de acesso'),array('Programas')),
            'one_time_keyboard' => true,
            'resize_keyboard' => true)));break;
       case "Outros":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual seu problema?', 'reply_markup' => array(
            'keyboard' => array(array('VOIP'), array('Rede'),array('Outro')),
            'one_time_keyboard' => true,
           'resize_keyboard' => true)));break;
       case "Rede":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual seu problema?', 'reply_markup' => array(
            'keyboard' => array(array('Acesso no Sigel'), array('Acesso a Rede'),array('Outro Problema de Rede')),
            'one_time_keyboard' => true,
            'resize_keyboard' => true)));
       case "Outro Banco de Dados":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Descreva seu problema e o banco:'));
            $dbhandle = sqlite_open("/tmp/ladetecbot.db");
            $return = sqlite_query($dbhandle,
              "Update tmp set assunto='1', local='LBCD' where id='".$chat_id."';"
            );
       case "Lims":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Descreva seu problema e o local caso necessário:'));
            $dbhandle = sqlite_open("/tmp/ladetecbot.db");
            $return = sqlite_query($dbhandle,
              "Update tmp set assunto='19', local='LBCD' where id='".$chat_id."';"
            );break;
       case "Soluções":
       case "LBCD":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Descreva seu problema e o local caso necessário:'));
            $dbhandle = sqlite_open("/tmp/ladetecbot.db");
            $return = sqlite_query($dbhandle,
              "Update tmp set assunto='1', local='LBCD' where id='".$chat_id."';"
            );break;
       case "Acesso a Rede":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Descreva seu problema e o local caso necessário:'));
            $dbhandle = sqlite_open("/tmp/ladetecbot.db");
            $return = sqlite_query($dbhandle,
              "Update tmp set assunto='2', local='LBCD' where id='".$chat_id."';"
            );break;
       case "Acesso no Sigel":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Descreva seu problema e o local caso necessário:'));
            $dbhandle = sqlite_open("/tmp/ladetecbot.db");
            $return = sqlite_query($dbhandle,
              "Update tmp set assunto='10', local='LBCD' where id='".$chat_id."';"
            );break;
       case "Outro" :
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual o equipamento? E onde ele se encontra?'));
            $dbhandle = sqlite_open("/tmp/ladetecbot.db");
            $return = ($dbhandle,
              "Update tmp set assunto='12' where id='".$chat_id."';"
            );break;
       case "Programas":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual o equipamento? E onde ele se encontra?'));
            $dbhandle = sqlite_open("/tmp/ladetecbot.db");
            $return = sqlite_query($dbhandle,
              "Update tmp set assunto='5' where id='".$chat_id."';"
            );break;
       case "Outro Equipamento":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual o equipamento? E onde ele se encontra?'));
            $dbhandle = sqlite_open("/tmp/ladetecbot.db");
            $return = sqlite_query($dbhandle,
              "Update tmp set assunto='3' where id='".$chat_id."';"
            );break;
       case "Computador ou Periférico" :
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual o equipamento? E onde ele se encontra?'));
            $dbhandle = sqlite_open("/tmp/ladetecbot.db");
            $return = sqlite_query($dbhandle,
              "Update tmp set assunto='3' where id='".$chat_id."';"
            );break;
       case "Impressora":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual o equipamento? E onde ele se encontra?'));
            $dbhandle = sqlite_open("/tmp/ladetecbot.db");
            $return = sqlite_query($dbhandle,
              "Update tmp set assunto='7' where id='".$chat_id."';"
            );break;
       case "Controle de acesso":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual o equipamento? E onde ele se encontra?'));
            $dbhandle = sqlite_open("/tmp/ladetecbot.db");
            $return = sqlite_query($dbhandle,
              "Update tmp set assunto='18' where id='".$chat_id."';"
            );break;
       case "VOIP":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual o equipamento? E onde ele se encontra?'));
            $dbhandle = sqlite_open("/tmp/ladetecbot.db");
            $return = sqlite_query($dbhandle,
              "Update tmp set assunto='".$text."' where id='".$chat_id."';"
            );break;
       default:
            $dbhandle = sqlite_open("/tmp/ladetecbot.db");
            $return = sqlite_query($dbhandle,"Select * from chamados where chat='".$chat_id."';");
            if($return->fetchArray();) {
              $resx = $return->fetchArray();
              $return = sqlite_query($dbhandle,
                "UPDATE chamados set problema="$resx[3]."\n".$message['from']['first_name'].": ".$resx[0]." where id=".$pass[1].";"
              );
              $return = sqlite_query($dbhandle,
                "SELECT t.chat FROM chamados c ti t WHERE t.id=c.id_ti AND c.chat='".$chat_id."';"
              );
              $resx = $return->fechArray();
              apiRequestJson("sendMessage", array('chat_id' => $resx[0], "text" => "'".$message['from']['first_name'].": ".$text."'"));
            }
            else{
              $return = sqlite_query($dbhandle,"Select * from tmp where id='".$chat_id."';");
              $resx = $return->fetchArray();
              if($resx[1] === ''){
                'keyboard' => array(array('Banco de Dados'), array ('Equipamentos de Informática ou Programas'), array ('Outros')),
                'one_time_keyboard' => true,
                'resize_keyboard' => true)));
              }
              else if($resx[2] === ''){
                apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Descreva seu problema:'));
                $result = sqlite_query($dbhandle,
                  "Update tmp set local='".$text."' where id='".$chat_id."';"
                );
              }
              else {
                apiRequestJson("sendMessage", array('chat_id' => $chat_id , "text" => 'A equipe de TI estrá empenhada em resolver seu problema.'));
                apiRequestJson("sendMessage", array('chat_id' => '-116137583' , "text" => $message['from']['first_name'].' '.$message['from']['last_name'].', precisa de ajuda com '.$resx[1].', no '.$resx[2].', dizendo : '.$text));*/
                apiSendMail($message['from']['first_name']." ".$message['from']['last_name']."¨".$resx[1]."¨".$resx[2]."¨".$text);
                $return = sqlite_query($dbhandle,
                  "Insert into chamados values (, ,'".$chat_id."','".$message['from']['first_name'].' '.$message['from']['last_name'].', precisa de ajuda com '.$resx[1].', no '.$resx[2].', dizendo : '.$text."');"
                );
                $return = sqlite_query($dbhandle,
                  "Delete from tmp where id='".$chat_id."';"
                );
              }
            }
          }//fecha case
      }
      $dbhandle->close();
  }//fecha if(isset($message['text']))
}//fecha função

define('WEBHOOK_URL', 'https://ladetec.iq.ufrj.br/ladetecbot.php' );

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update["message"])) {
  //error_log2(implode($update["message"]));
  processMessage($update["message"],$update["user"]);
}
