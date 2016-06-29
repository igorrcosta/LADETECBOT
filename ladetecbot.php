<?php

define('BOT_TOKEN', '191671065:AAFMbIGQgsY2V2td099yt9I9HzCC6cl6t7Y');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

function apiRequestWebhook($method, $parameters) {
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

  header("Content-Type: application/json");
  echo json_encode($parameters);
  return true;
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
  $message_id = $message['message_id'];
  $chat_id = $message['chat']['id'];
  if (isset($message['text'])) {
    // incoming text message
    $text = $message['text'];

    if ($text === "/start") {
      apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual seu problema?', 'reply_markup' => array(
        'keyboard' => array(array('Banco de Dados'), array ('Equipamentos de Informática ou Programas'), array ('Outros')),
        'one_time_keyboard' => true,
        'resize_keyboard' => true)));
    } else if ($text === "Banco de Dados") {
      apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Em qual banco esta tendo problemas?', 'reply_markup' => array(
        'keyboard' => array(array('Lims'), array ('Soluções'), array ('LBCD'), array ('Outro Banco de Dados')),
        'one_time_keyboard' => true,
        'resize_keyboard' => true)));
    } else if ($text === "Equipamentos de Informática ou Programas") {
      apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual tipo de equipamento?', 'reply_markup' => array(
        'keyboard' => array(array('Computador ou Periférico'), array('Impressora'),array('Controle de acesso'),array('Outro Equipamento')),
        'one_time_keyboard' => true,
        'resize_keyboard' => true)));
    } else if ($text === "Outros") {
      apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Qual seu problema?', 'reply_markup' => array(
        'keybaord' => array(array('VOIP'), array('Rede'),array('Outro')),
        'one_time_keyboard' => true,
        'resize_keyboard' => true)));
    }else if ($text === "Lims" || $text === "Soluções" || $text === "LBCD" || $text === "Rede") {
      apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Descreva seu problema:'));
    }else if ($text === "Outro Banco de Dados") {
      apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Descreva seu problema e o banco:'));
    }else if ($text === "Outro Equipamento") {
      apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Descreva seu problema e o equipamento:'));
    }else if ($text === "Outro") {
      apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Onde se encontra o problema?'));
    }else if ($text === "Computador ou Periférico" || $text === "Impressora" || $text === "Controle de acesso" || $text === "VOIP") {
      apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Onde se encontra o equipamento?'));
    }else if (false) {// tratar esse caso qnd o canco estiver pronto
      apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Descreva seu problema:'));
    }else {
      apiRequestWebhook("sendMessage", array('chat_id' => $chat_id, "text" => 'A equipe de TI estrá empenhada em resolver seu problema.'));
    }
    }
    else {
    apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Diga /start para começar, e selecione nossas opções.'));
  }
}


define('WEBHOOK_URL', 'https://ladetecbot-mad27.c9users.io/ladetecbot.php' );

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update["message"])) {
  processMessage($update["message"]);
}
