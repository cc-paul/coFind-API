<?php
	require_once('../helper/conn.php');
	require_once('../model/response.php');
	require_once('../helper/date.php');
	require_once('../helper/message.php');
	require_once('../helper/utils.php');

	try {
		$writeDB = DB::connectionWriteDB();
	} catch(PDOException $ex) {
		error_log("Connection Error - ".$ex, 0);
		sendResponse(500,false,"Database Connection Error");
	}

	if (array_key_exists("sessionid", $_GET)) {

	} else if (empty($_GET)) {

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			sendResponse(405,false,"Request method not found");
		}

		sleep(1);

		if(!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] !== 'application/json') {
			sendResponse(400,false,"Content type header is not JSON");
		}

		$rawPOSTData = file_get_contents('php://input');

		if (!$jsonData = json_decode($rawPOSTData)) {
			sendResponse(400,false,"Request body is not JSON");
		}

		if (!isset($jsonData->username) || !isset($jsonData->password)) {
			sendResponse(400,false,"Username/Email Address and Password must be provided");
		}

		if (strlen($jsonData->username) < 1 || strlen($jsonData->password) < 1) {
			sendResponse(400,false,"Username/Email Address and Password cannot be blank");
		}

		try {

			$username = $jsonData->username;
			$password = $jsonData->password;

			$query = $writeDB->prepare('
				SELECT 
				    *
				FROM 
				    cf_registration 
				WHERE 
				    (username = :username OR emailAddress = :emailAddress) 
				    AND isAccountVerified = 1 
				    AND isGoogleSignIn = 0 
				    AND isActive = 1
 			');
			$query->bindParam(":username",$username,PDO::PARAM_STR);
			$query->bindParam(":emailAddress",$username,PDO::PARAM_STR);
			$query->execute();

			$rowCount = $query->rowCount();

			if ($rowCount === 0) {
				sendResponse(401,false,"Username/Email Address or Password does not exist");
			}

			$row = $query->fetch(PDO::FETCH_ASSOC);

			$returned_id = $row["id"];
			$returned_fullname = $row["lastName"] . ", " . $row["firstName"] . "" . $row["middleName"];
			$returned_username = $row["username"];
			$returned_password = $row["password"];
			$returned_useractive = $row["isActive"];
			$returned_loginattempts = $row["loginAttempts"];

			if ($returned_useractive !== 1) {
				sendResponse(401,false,"Account not active");
			}

			if ($returned_loginattempts >= 3) {
				sendResponse(401,false,"User account is currently locked out");
			}

			if (!password_verify($password, $returned_password)) {
				$query = $writeDB->prepare('UPDATE cf_registration SET loginAttempts = loginAttempts + 1 WHERE id = :id');
				$query->bindParam(":id",$returned_id,PDO::PARAM_INT);
				$query->execute();

				sendResponse(401,false,"Username or Password is incorrect");
			}

			$accesstoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
			$refreshtoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
			$access_token_expiry_seconds = 1200;
			$refresh_token_expiry_seconds = 1209600;

		} catch (PDOException $ex) {
			sendResponse(500,false,"There was an issue logging in".$ex);
		}

		try {
			$writeDB->beginTransaction();
			$currentDateAndTime = getCurrentDateAndTime();

			$query = $writeDB->prepare('UPDATE cf_registration SET loginAttempts = 0 WHERE id = :id');
			$query->bindParam(':id',$returned_id,PDO::PARAM_INT);
			$query->execute();

			$query = $writeDB->prepare('INSERT INTO cf_sessions (userid,accesstoken,accesstokenexpiry,refreshtoken,refreshtokenexpiry) VALUES (:userid,:accesstoken,DATE_ADD(:accesstokenexpiry, INTERVAL :accesstokenexpiryseconds SECOND),:refreshtoken,DATE_ADD(:refreshtokenexpiry, INTERVAL :refreshtokenexpiryseconds SECOND))');
			$query->bindParam(":userid",$returned_id,PDO::PARAM_INT);
			$query->bindParam(":accesstoken",$accesstoken,PDO::PARAM_STR);
			$query->bindParam(":accesstokenexpiryseconds",$access_token_expiry_seconds,PDO::PARAM_INT);
			$query->bindParam(":refreshtoken",$refreshtoken,PDO::PARAM_STR);
			$query->bindParam(":refreshtokenexpiryseconds",$refresh_token_expiry_seconds,PDO::PARAM_INT);
			$query->bindParam(":accesstokenexpiry",$currentDateAndTime,PDO::PARAM_INT);
			$query->bindParam(":refreshtokenexpiry",$currentDateAndTime,PDO::PARAM_INT);
			$query->execute();

			$lastSessionID = $writeDB->lastInsertId();

			$writeDB->commit();

			$returnData = array();
			// $returnData['session_id']               = intval($lastSessionID);
			// $returnData['first_name']               = $row["firstName"];
			// $returnData['middle_name']              = $row["middleName"];
			// $returnData['last_name']                = $row["lastName"];
			// $returnData['email_address']            = $row["emailAddress"];
			// $returnData['access_token']             = $accesstoken;
			// $returnData['access_token_expires_in']  = $access_token_expiry_seconds;
			// $returnData['refresh_token']            = $refreshtoken;
			// $returnData['refresh_token_expires_in'] = $refresh_token_expiry_seconds;
			$returnData['user_id']                  = $returned_id;

			sendResponse(201,true,"Account logged in successfully",$returnData);
		} catch (PDOException $ex) {
			sendResponse(500,false,"There was an issue logging in. Please try again later" . $ex);
		}
	} else { 
		sendResponse(404,false,"Endpoint not found");
	}
?>