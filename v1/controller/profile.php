<?php
	require_once('../helper/conn.php');
	require_once('../model/response.php');
	require_once('../helper/message.php');
	require_once('../helper/utils.php');

	try {
		$writeDB = DB::connectionWriteDB();
		$readDB = DB::connectionReadDB();
	} catch (PDOException $ex) {
		error_log("Connection Error - ".$ex, 0);
		sendResponse(500,false,"Database Connection Error");
	}

	$method = $_SERVER['REQUEST_METHOD'];

	if ($method === 'POST') {
		if(!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] !== 'application/json') {
			sendResponse(400,false,"Content type header is not JSON");
		}

		$rawPOSTData = file_get_contents('php://input');

		if (!$jsonData = json_decode($rawPOSTData)) {
			sendResponse(400,false,"Request body is not JSON");
		}

		$command = $jsonData->command;

		if ($command == "PROFILE_PIC") {
			if (
				!isset($jsonData->user_id) || 
				!isset($jsonData->imageLink)
			) {
				sendResponse(400,false,"The JSON body you sent has incomplete parameters");
			}

			try {
				$query = $writeDB->prepare("UPDATE cf_registration SET imageLink = :imageLink WHERE id = :id");
				$query->bindParam(':imageLink',$jsonData->imageLink,PDO::PARAM_STR);
				$query->bindParam(':id',$jsonData->user_id,PDO::PARAM_INT);
				$query->execute();

				$rowCount = $query->rowCount();

				if ($rowCount === 0) {
					sendResponse(500,false,"Unable to change profile. Please try again later");
				}

				sendResponse(201,true,"Profile has been changed completely.");
			} catch (PDOException $ex) {
				error_log("Database query error: ".$ex,0);
				sendResponse(500,false,"There was an issue changing profile. Please try again");
			}
		} else if ($command === "CHANGE_PASSWORD") {
			if (
				!isset($jsonData->user_id) || 
				!isset($jsonData->password)	
			) {
				sendResponse(400,false,"The JSON body you sent has incomplete parameters");
			}


			$has_eightchar = strlen($jsonData->password) >= 8 ? true : false;
			$has_uppercase = preg_match('@[A-Z]@', $jsonData->password);
			$has_number    = preg_match('@[0-9]@', $jsonData->password);
			$has_specialChars = preg_match('/[^a-zA-Z0-9]/', $jsonData->password);
			$arrErrors = array();

			if (!$has_eightchar) {
				array_push($arrErrors,"Password must be at least 8 characters");
			}

			if (!$has_uppercase) {
				array_push($arrErrors, "Password must have a capital letter");
			}

			if (!$has_number) {
				array_push($arrErrors,"Password must have a number");
			}

			if (!$has_specialChars) {
				array_push($arrErrors,"Password must have a special character");
			}

			if (count($arrErrors) >= 1) {
				sendResponse(400,false,"There are errors in your password",$arrErrors,false);
			}


			$hashed_password = password_hash($jsonData->password, PASSWORD_DEFAULT);

			$query = $writeDB->prepare("UPDATE cf_registration SET password = :password WHERE id = :id");
			$query->bindParam(':password',$hashed_password,PDO::PARAM_STR);
			$query->bindParam(':id',$jsonData->user_id,PDO::PARAM_STR);
			$query->execute();

			$rowCount = $query->rowCount();

			if ($rowCount === 0) {
				sendResponse(500,false,"There was an issue changing your password.Please try again");
			}

			sendResponse(201,true,"Password has been updated. You can now login with your new credentials");
		} else if ($command === "PROFILE_DETAILS") {
			if (
				!isset($jsonData->user_id) || 
				!isset($jsonData->firstName) || 
				!isset($jsonData->middleName) || 
				!isset($jsonData->lastName) || 
				!isset($jsonData->mobileNumber) || 
				!isset($jsonData->address)
			) {
				sendResponse(400,false,"The JSON body you sent has incomplete parameters");
			}


			if (strlen($jsonData->firstName) < 1) {
				sendResponse(400,false,"First Name must not be empty");
			} else if (strlen($jsonData->firstName > 255)) {
				sendResponse(400,false,"First Name is too long");
			}

			if (strlen($jsonData->middleName) > 255) {
				sendResponse(400,false,"Middle Name is too long");
			}

			if (strlen($jsonData->lastName) < 1) {
				sendResponse(400,false,"Last Name must not be empty");
			} else if (strlen($jsonData->lastName) > 255) {
				sendResponse(400,false,"Last Name is too long");
			}

			if (strlen($jsonData->mobileNumber) != 11) {
				sendResponse(400,false,"Mobile Number must be 11 digits");
			}

			if (strlen($jsonData->address) < 1) {
				sendResponse(400,false,"Address must not be empty");
			}

			try {
				$query = $writeDB->prepare("
					UPDATE cf_registration SET 
						firstName = :firstName,
						middleName = :middleName,
						lastName = :lastName,
						mobileNumber = :mobileNumber,
						address = :address
					WHERE
						id = :id
				");
				$query->bindParam(':firstName',$jsonData->firstName,PDO::PARAM_STR);
				$query->bindParam(':middleName',$jsonData->middleName,PDO::PARAM_STR);
				$query->bindParam(':lastName',$jsonData->lastName,PDO::PARAM_STR);
				$query->bindParam(':mobileNumber',$jsonData->mobileNumber,PDO::PARAM_STR);
				$query->bindParam(':address',$jsonData->address,PDO::PARAM_STR);
				$query->bindParam(':id',$jsonData->user_id,PDO::PARAM_STR);
				$query->execute();

				$rowCount = $query->rowCount();

				if ($rowCount === 0) {
					sendResponse(500,false,"Nothing to change. Please update some data");
				}

				sendResponse(201,true,"User account has been updated.");
			} catch (PDOException $ex) {
				error_log("Database query error: ".$ex,0);
				sendResponse(500,false,"There was an error updating account. Please try again ");
			}
		}
	} else {
		sendResponse(404,false,"Endpoint not found");
	}
?>