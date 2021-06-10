
<?php
  
class WebSocket
 {
  
     private $message = null;
  
     private $ws = null;
  
     private $usr_array = array();
  
     private $user_hash = "user_hash";
  
     private $conn_hash = "conn_hash";
  
     private $redis_conn = null;
     private $savetoken='';
     public function __construct()
     {
 //Connect to redis server 
          
         $this->redis_conn = new Redis();
  
         $this->redis_conn->connect('******', 6379);//your ip address
         
         $this->ws = new swoole_websocket_server("0.0.0.0", 9502);
  
                  //Monitor the WebSocket connection open event
         $this->ws->on('open', function ($ws, $request) {
  
         });
  
                  //Monitor WebSocket message events
         $this->ws->on('message', function ($ws, $obj) {
             echo "Message: {$obj->data}\n";
               //this->changeT();
              //$this->changeT();
             $data_json = json_decode($obj->data, true);
  
             $this->message = $data_json['data'];
  
             switch ($data_json['code']) {
                 case 1:
                     //s
                     $this->login($obj->fd);
                     break;
                 case 2:
                     $this->sendMsg();
                     break;
                 case 3:
                     $this->register($obj->fd);
                     break;
                case 5:
                    $this->checktoken($obj->fd);
                 default:
                     break;
             }
         });
  
                  //Monitor the WebSocket connection close event
         $this->ws->on('close', function ($ws, $fd) {
             echo "client-{$fd} is closed\n";
                          //Refresh friends list
     $this->redis_conn->hDel($this->conn_hash, $fd);
         $this->send(true, 0, $this->echoMsg(4, '', $this->all_in_line()));
         $this->redis_conn->close();
  
         });
  
         $this->ws->start();
  
     }
  
 // User registration
     private function register($id)
     {
                  # phone,username,password detection registration information must not be empty
  
         if (!$this->checkArray($this->message[0])) {
             $this->send(false, $id, $this->echoMsg(1000, 'have null data!', ''));
             return;
         }
  
         $user_phone = $this->message[0]['phone'];
  
         $user_array = array(
             "password" => $this->message[0]['password'],
             "username" => $this->message[0]['username'],
         );
  
         if (!empty($this->getHash($this->user_hash, $user_phone))) {
             $this->send(false, $id, $this->echoMsg(1000, 'this phone is register!', ''));
         } else {
             $this->setHash($this->user_hash, $user_phone, serialize($user_array));
             $this->send(false, $id, $this->echoMsg(3, '', ''));
         }
     }
  
     private function login($id)
     {
         $psd = $this->message[0]['psd'];
         $usr = $this->message[0]['usr'];
        
  
         $in_res = $this->getHash($this->user_hash, $usr);
         if (empty($in_res)) {
             $this->send(false, $id, $this->echoMsg(1000, 'user not exist,please register!', $this->message));
         } else {
             
             $in_res = unserialize($in_res);
             
             if ($in_res['password'] == $psd) {
                $this->savetoken=md5($this->message[0]['psd'].$this->message[0]['usr']);
                #var_dump($savetoken);
                 //save user info
                 $this->setHash($this->conn_hash, $id, $in_res['username']);
  
                 $this->send(false, $id, $this->echoMsg(1, array("id" => $id, "username" => $in_res['username'],'token'=>$this->savetoken,"usr_phone"=>$this->message[0]['usr']), ''));
  
                 //change friends item
                 $this->send(true, 0, $this->echoMsg(4, '', $this->all_in_line()));
             } else {
                 $this->send(false, $id, $this->echoMsg(1000, 'password is error!', ''));
             }
         }
     }









     private function checktoken($id)
     {
         $checktoken = $this->message[0]['token'];
         $usr = $this->message[0]['usr'];
         $in_res = $this->getHash($this->user_hash, $usr);
         if (empty($in_res)) {
             $this->send(false, $id, $this->echoMsg(1000, 'no token', $this->message));
         } else {
             $in_res = unserialize($in_res);
             $user_token = md5($in_res['password'].$this->message[0]['usr']);
             if ($user_token == $checktoken) {
                //$this->savetoken=md5($this->message[0]['psd'].$this->message[0]['usr']);
                #var_dump($savetoken);
                 //save user info
                 $this->setHash($this->conn_hash, $id, $in_res['username']);
                 $this->send(false, $id, $this->echoMsg(1, array("id" => $id, "username" => $in_res['username'],'token'=>$this->savetoken,"usr_phone"=>$this->message[0]['usr']), ''));
                 //change friends item
                 $this->send(true, 0, $this->echoMsg(4, '', $this->all_in_line()));
  
                 
             } else {
                 $this->send(false, $id, $this->echoMsg(1000, 'token error', $user_token));
             }
         }
     }




  
     //search all in line
     private function all_in_line()
     {
         $res = $this->redis_conn->hGetAll($this->conn_hash);
         return array($res);
     }
     // send msg   
     private function send($is_all = false, $id = 0, $msg = '')
     {
         if ($is_all) {
             foreach ($this->ws->connections as $fd) {
                 $this->ws->push($fd, $msg);
             }
         } else {
             $this->ws->push($id, $msg);
         }
     }
  
 //Send the message, and according to the sending object ID, if it is 0, it is sent to everyone
     private function sendMsg()
     {
         $send_id = intval($this->message[0]['to']);
          var_dump($this->savetoken);
          if ($this->savetoken==$this->message[0]['token']){
            if ($send_id != 0) {
                //person
     
                $this->send(false, $send_id, $this->echoMsg(2, '', $this->message[0]));
     
            } else {
                //all
                $this->send(true, 0, $this->echoMsg(2, '', $this->message[0]));
            }

          }
          else {

            $this->send(false ,0, $this->echoMsg(5, 'token is error!', ''));
             }
          }
         

  
     
  
  
          //Return json string
     private function echoMsg($code, $msg, $data)
     {
         $redata = array();
                  //$data is not empty
         if (!empty($data)) {
                          if (is_array($data)) {//pass in array
                 $redata = $data;
                          } else {//Incoming string
                 $redata = $data;
             }
         }
                  if ($code == 0) {//identification code is 0
             $reMsg = array('code' => $code, 'msg' => $msg);
             return json_encode($reMsg);
                  } else {//The identification code is not 0
             $reMsg = array(
                 'code' => $code,
                 'msg' => $msg,
                 'data' => $redata
             );
             return json_encode($reMsg);
         }
     }
  
     //save login state
     public function setHash($data = '', $key = '', $value = '')
     {
         # hash
         $in_login = $this->redis_conn->hGet($data, $key);
         if (empty($in_login)) {
             $this->redis_conn->hSet($data, $key, $value);
             return true;
         } else {
             return false;
         }
     }
  
     public function getHash($data = '', $key = '')
     {
         return $this->redis_conn->hGet($data, $key);
     }
  
     public function checkArray($data_array)
     {
         foreach ($data_array as $key => $value) {
             if (empty($value)) {
                 return false;
             }
         }
         return true;
     
        }


       
        //public $Expires;
        public function changeT() // => $privateUrl
        {   $time=time()-60;
            echo($time . "<br />");
            $currentT=$this->savetoken;
            while($time = time()) {
                $randnum=rand(1,9);
                var_dump($randnum);
            if (time() - $time >= 60) {
                $this->savetoken=md5($this->message[0]['psd'].$this->message[0]['usr'].$randnum);
                return $this->savetoken;
                } else {
                   return $currentT;
                }
            }
             
        }

}

new WebSocket();
