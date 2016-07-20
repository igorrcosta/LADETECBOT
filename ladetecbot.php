<?php
define('BOT_TOKEN', '');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

require 'vendor/autoload.php';
use Mailgun\Mailgun;


$error_file = fopen('/tmp/bot_error.log','w');
fwrite($error_file, 'file is open');
fclose($error_file);

function error_log2($error_msg)
{
    $error_file = fopen('/tmp/bot_error.log','w');
    fwrite($error_file, $error_msg);
    fclose($error_file);
}

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
    error_log2("Curl returned error $errno: $error\n");
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
    error_log2("Request has failed with error {$response['error_code']}: {$response['description']}\n");
    if ($http_code == 401) {
      throw new Exception('Invalid access token provided');
    }
    return false;
  } else {
    $response = json_decode($response, true);
    if (isset($response['description'])) {
      error_log2("Request was successfull: {$response['description']}\n");
    }
    $response = $response['result'];
  }

  return $response;
}

function apiRequest($method, $parameters) {
  if (!is_string($method)) {
    error_log2("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log2("Parameters must be an array\n");
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

function apiSendMail($text){

# Instantiate the client.
$key = ''; 
$client = new \Http\Adapter\Guzzle6\Client();
$mailgun = new \Mailgun\Mailgun($key,$client);


$domain = "sandbox57fbf15331ae4b308621142b6e48acb1.mailgun.org";

# Make the call to the client.
$result = $mailgun->sendMessage($domain, array(
    'from'    => 'Mailgun Sandbox <postmaster@sandbox57fbf15331ae4b308621142b6e48acb1.mailgun.org>',
    'to'      => 'Thiago Abrantes <thiagosouza@iq.ufrj.br>',
    'subject' => '[BOT TELEGRAM]',
    'text'    => $text."¨ALGUMACOISAMUITOUNICABEMDIVERTIDA"
));
}

function apiRequestJson($method, $parameters) {
  if (!is_string($method)) {
    error_log2("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log2("Parameters must be an array\n");
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
      error_log2($text);
      switch ($text){
        case "/start": 
              apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual seu problema?', 'reply_markup' => array(
              'keyboard' => array(array('Banco de Dados'), array ('Equipamentos de Informática ou Programas'), array ('Outros')),
              'one_time_keyboard' => true,
              'resize_keyboard' => true)));
              
              $dbhandle=sqlite_open("/tmp/ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Delete from tmp where id='".$chat_id."';"
              );
              $return=sqlite_query($dbhandle,
                "Insert into tmp values (".$chat_id.", '', '');"
              );
              break;
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
            
            $dbhandle=sqlite_open("/tmp/ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Update tmp set assunto='1', local='LBCD' where id='".$chat_id."';"
              );  
      case "Lims":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Descreva seu problema e o local caso necessário:'));
            
            $dbhandle=sqlite_open("/tmp/ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Update tmp set assunto='19', local='LBCD' where id='".$chat_id."';"
              );break;
      case "Soluções":
      case "LBCD":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Descreva seu problema e o local caso necessário:'));
            
            $dbhandle=sqlite_open("/tmp/ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Update tmp set assunto='1', local='LBCD' where id='".$chat_id."';"
              );break;
      case "Acesso a Rede": 
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Descreva seu problema e o local caso necessário:'));
            
            $dbhandle=sqlite_open("/tmp/ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Update tmp set assunto='2', local='LBCD' where id='".$chat_id."';"
              );break;
      case "Acesso no Sigel":
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Descreva seu problema e o local caso necessário:'));
            
            $dbhandle=sqlite_open("/tmp/ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Update tmp set assunto='10', local='LBCD' where id='".$chat_id."';"
              );break;
      case "Outro" :
        apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual o equipamento? E onde ele se encontra?'));
            $dbhandle=sqlite_open("/tmp/ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Update tmp set assunto='12' where id='".$chat_id."';"
              );break; 
      case "Programas":
        apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual o equipamento? E onde ele se encontra?'));
            $dbhandle=sqlite_open("/tmp/ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Update tmp set assunto='5' where id='".$chat_id."';"
              );break; 
      case "Outro Equipamento":  
        apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual o equipamento? E onde ele se encontra?'));
            $dbhandle=sqlite_open("/tmp/ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Update tmp set assunto='3' where id='".$chat_id."';"
              );break; 
      case "Computador ou Periférico" :
        apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual o equipamento? E onde ele se encontra?'));
            $dbhandle=sqlite_open("/tmp/ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Update tmp set assunto='3' where id='".$chat_id."';"
              );break; 
      case "Impressora":        
        apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual o equipamento? E onde ele se encontra?'));
            $dbhandle=sqlite_open("/tmp/ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Update tmp set assunto='7' where id='".$chat_id."';"
              );break; 
      case "Controle de acesso":
        apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual o equipamento? E onde ele se encontra?'));
            $dbhandle=sqlite_open("/tmp/ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Update tmp set assunto='18' where id='".$chat_id."';"
              );break; 
      case "VOIP":  
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual o equipamento? E onde ele se encontra?'));
            $dbhandle=sqlite_open("/tmp/ladetecbot.db");
              $return=sqlite_query($dbhandle,
                "Update tmp set assunto='".$text."' where id='".$chat_id."';"
              );break;         
      default:    
            $dbhandle=sqlite_open("/tmp/ladetecbot.db");
            $return=sqlite_query($dbhandle,"Select * from tmp where id='".$chat_id."';");
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
                "Update tmp set local='".$text."' where id='".$chat_id."';"
                );
            }
            else {
              apiRequestJson("sendMessage", array('chat_id' => $chat_id , "text" => 'A equipe de TI estrá empenhada em resolver seu problema.'));
              
              /*
              apiRequestJson("sendMessage", array('chat_id' => '-116137583' , "text" => $message['from']['first_name'].' '.$message['from']['last_name'].', precisa de ajuda com '.$resx[1].', no '.$resx[2].', dizendo : '.$text));
              */
              
              apiSendMail(
                $message['from']['first_name']." ".$message['from']['last_name']."¨".$resx[1]."¨".$resx[2]."¨".$text
                /*
                array(
                'from'    => $message['from']['first_name'].' '.$message['from']['last_name'],
                'where'   => $resx[2],
                'subject' => $resx[1],
                'problem' => '$text'
                )
                */
                );
              
              
              $return=sqlite_query($dbhandle,
                "Delete from tmp where id='".$chat_id."';"
              );
              
            }
        }
    $dbhandle->close();
  }
}


define('WEBHOOK_URL', 'https://ladetec.iq.ufrj.br/ladetecbot.php' );

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update["message"])) {
  #error_log2(implode($update["message"]));
  processMessage($update["message"],$update["user"]);
}
