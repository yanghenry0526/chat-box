<?php 
//用戶名稱的顏色，方便辨識不同client
$colors = array('#007AFF','#FF7000','#FF7000','#15E25F','#CFC700','#CFC700','#CF1100','#CF00BE','#F00');
$color_pick = array_rand($colors);
?>

<!DOCTYPE html>
<html>
<head>
<title>Chat box</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style type="text/css">
.chat-wrapper {
	font: bold 11px/normal 'lucida grande', tahoma, verdana, arial, sans-serif;
    background: #BD7B00;
    padding: 20px;
    margin: 20px auto;
    box-shadow: 2px 2px 2px 0px #00000017;
	max-width:700px;
	min-width:500px;
}
#message-box {
    width: 97%;
    display: inline-block;
    height: 300px;
    box-shadow: inset 0px 0px 2px #00000017;
    overflow: auto;
    padding: 10px;
	background-color: #e1e2c3;
}
.user-panel{
    margin-top: 10px;
}
input[type=text]{
    border: none;
    padding: 5px 5px;
    box-shadow: 2px 2px 2px #0000001c;
}
input[type=text]#name{
    width:20%;
}
input[type=text]#message{
    width:60%;
}
button#send-message {
    border: none;
    padding: 5px 15px;
    background: #FFD78C;
    box-shadow: 2px 2px 2px #0000001c;
}
</style>
</head>
<body>

<div class="chat-wrapper">
<div id="message-box"></div>
<div class="user-panel">
<input type="text" name="name" id="name" placeholder="Your Name" maxlength="15" />
<input type="text" name="message" id="message" placeholder="Type your message here..." maxlength="100" />
<button id="send-message">Send</button>
</div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script language="javascript" type="text/javascript">  
	//創建一個新的websocket 物件.
	var msgBox = $('#message-box');
	var wsUri = "ws://localhost:8080"; 	//port 8080 要和server端一樣
	websocket = new WebSocket(wsUri); 
	
	websocket.onopen = function(ev) { // 連線成功顯示訊息
		msgBox.append('<div class="system_msg" style="color:#bbbbbb">Welcome to "Henry WebSocket Chat box"!</div>'); //notify user
	}
	
	//沒連接上發送錯誤
	websocket.onerror	= function(ev){ msgBox.append('<div class="system_error">Error Occurred - ' + ev.data + '</div>'); }; 
	websocket.onclose 	= function(ev){ msgBox.append('<div class="system_msg">Connection Closed</div>'); }; 

	// 從server端接收到的訊息
	websocket.onmessage = function(ev) {
		var response 		= JSON.parse(ev.data); //解開Json檔
		
		var res_type 		= response.type; //message type
		var user_message 	= response.message; //message text
		var user_name 		= response.name; //user name
		var user_color 		= response.color; //color

		switch(res_type){
			case 'usermsg':
				msgBox.append('<div><span class="user_name" style="color:' + user_color + '">' + user_name + '</span> : <span class="user_message">' + user_message + '</span></div>');
				break;
			case 'system':
				msgBox.append('<div style="color:#bbbbbb">' + user_message + '</div>');
				break;
		}
		msgBox[0].scrollTop = msgBox[0].scrollHeight; //scroll message 

	};
	


	//Message send button
	$('#send-message').click(function(){
		send_message();
	});
	
	//User hits enter key 
	$( "#message" ).on( "keydown", function( event ) {
	  if(event.which==13){
		  send_message();
	  }
	});
	
	//Send message
	function send_message(){
		var message_input = $('#message'); //user message text
		var name_input = $('#name'); //user name
		
		if(message_input.val() == ""){ //沒輸入名字 報錯
			alert("Enter your Name please!");
			return;
		}
		if(message_input.val() == ""){ //沒輸入訊息 報錯
			alert("Enter Some message Please!");
			return;
		}

		//要傳給JSON的資料
			var msg = {
			message: message_input.val(),
			name: name_input.val(),
			color : '<?php echo $colors[$color_pick]; ?>'
		};
		//將資料從JSON傳給server
		websocket.send(JSON.stringify(msg));	
		message_input.val(''); //reset message input
	}
</script>
</body>
</html>
