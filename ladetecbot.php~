<?php

define('BOT_TOKEN', '191671065:AAFMbIGQgsY2V2td099yt9I9HzCC6cl6t7Y');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

function sqlite_open($location)
{
    $handle = new SQLite3($location);
    return $handle;
}

function sqlite_query($dbhandle,$query)
{
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
    error_log("Curl returned error $errno: $error\n");
    curl_close($handle);
    return false;
  }

  $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
  curl_close($handle);

  if ($http_code >= 500) {
    // do not wat to DDOS server if something goes wrong
    sleep(10);
    return false;
  } else if ($http_code != 200) {
    $response = json_decode($response, true);
    error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
    if ($http_code == 401) {
      throw new Exception('Invalid access token provided');
    }
    return false;
  } else {
    $response = json_decode($response, true);
    if (isset($response['description'])) {
      error_log("Request was successfull: {$response['description']}\n");
    }
    $response = $response['result'];
  }

  return $response;
}

function apiRequest($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  foreach ($parameters as $key => &$val) {
    // encoding to JSON array parameters, for example reply_markup
    if (!is_numeric($val) && !is_string($val)) {
      $val = json_encode($val);
    }
  }
  $url = API_URL.$method.'?'.http_build_query($parameters);

  $handle = curl_init($url);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);

  return exec_curl_request($handle);
}

function apiRequestJson($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  $handle = curl_init(API_URL);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);
  curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
  curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

  return exec_curl_request($handle);
}

function processMessage($message) {
  // process incoming message
  $chat_id = $message['chat']['id'];
  if (isset($message['text'])) {
    // incoming text message
    $text = $message['text'];

      switch ($text){
        case "/start": 
              apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual seu problema?', 'reply_markup' => array(
              'keyboard' => array(array('Banco de Dados'), array ('Equipamentos de Informática ou Programas'), array ('Outros')),
              'one_time_keyboard' => true,
              'resize_keyboard' => true)));
              
              $dbhandle=sqlite_open("ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Delete from temp where id='".$chat_id."';"
              );
              $return=sqlite_query($dbhandle,
                "Insert into temp values (".$chat_id.", '', '');"
              );break;
      case "Banco de Dados":
              apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Em qual banco esta tendo problemas?', 'reply_markup' => array(
              'keyboard' => array(array('Lims'), array ('Soluções'), array ('LBCD'), array ('Outro Banco de Dados')),
              'one_time_keyboard' => true,
              'resize_keyboard' => true)));break;
      case "Equipamentos de Informática ou Programas":
              apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual tipo de equipamento?', 'reply_markup' => array(
              'keyboard' => array(array('Computador ou Periférico'), array('Impressora'),array('Controle de acesso'),array('Outro Equipamento')),
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
            
            $dbhandle=sqlite_open("ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Update temp set assunto='".$text."', local='LBCD' where id='".$chat_id."';"
              );  
      case "Lims":
      case "Soluções":
      case "LBCD":
      case "Rede":          
      case "Acesso a Rede": 
      case "Acesso no Sigel":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Descreva seu problema e o local caso necessário:'));
            
            $dbhandle=sqlite_open("ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Update temp set assunto='".$text."', local='LBCD' where id='".$chat_id."';"
              );break;
      case "Outro" :
      case "Outro Problema de Rede":        
      case "Outro Equipamento":        
      case "Computador ou Periférico" :
      case "Impressora":        
      case "Controle de acesso":
      case "VOIP":  
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual o equipamento? E onde ele se encontra?'));
            $dbhandle=sqlite_open("ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Update temp set assunto='".$text."' where id='".$chat_id."';"
              );break;         
      default:    
            $dbhandle=sqlite_open("ladetecbot.db");
            $return=sqlite_query($dbhandle,"Select * from temp where id='".$chat_id."';");
            $resx = $return->fetchArray();
            
            if($resx[1]===''){
              apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Assunto inválido!', 'reply_markup' => array(
              'keyboard' => array(array('Banco de Dados'), array ('Equipamentos de Informática ou Programas'), array ('Outros')),
              'one_time_keyboard' => true,
              'resize_keyboard' => true)));
            }
            else if($resx[2]===''){
              apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Descreva seu problema:'));
              $result=sqlite_query($dbhandle,
                "Update temp set local='".$text."' where id='".$chat_id."';"
                );
            }
            else {
              apiRequestJson("sendMessage", array('chat_id' => $chat_id , "text" => 'A equipe de TI estrá empenhada em resolver seu problema.'));
              
              /*
              apiRequestJson("sendMessage", array('chat_id' => '-116137583' , "text" => $message['from']['first_name'].' '.$message['from']['last_name']));
              apiRequestJson("sendMessage", array('chat_id' => '-116137583' , "text" => 'Assunto: '.$resx[1]));
              apiRequestJson("sendMessage", array('chat_id' => '-116137583' , "text" => 'Local: '.$resx[2]));
              apiRequestJson("sendMessage", array('chat_id' => '-116137583' , "text" => 'Problema: '.$text));
              */
              
              /*
              adicionar banco helpdesk
              */
              
              $return=sqlite_query($dbhandle,
                "Delete from temp where id='".$chat_id."';"
              );
              
            }
        }
    $dbhandle->close();
  }
}


define('WEBHOOK_URL', 'https://ladetecbot-mad27.c9users.io/ladetecbot.php' );

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update["message"])) {
  processMessage($update["message"],$update["user"]);
}
