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
	$hashed_password = "";

	if ($method === 'POST') {
		if(!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] !== 'application/json') {
			sendResponse(400,false,"Content type header is not JSON");
		}

		$rawPOSTData = file_get_contents('php://input');

		if (!$jsonData = json_decode($rawPOSTData)) {
			sendResponse(400,false,"Request body is not JSON");
		}

		if (
			!isset($jsonData->firstName) || 
			!isset($jsonData->middleName) || 
			!isset($jsonData->lastName) || 
			!isset($jsonData->username) ||
			!isset($jsonData->emailAddress) || 
			!isset($jsonData->mobileNumber) || 
			!isset($jsonData->address) || 
			!isset($jsonData->password) || 
			!isset($jsonData->rPassword) || 
			!isset($jsonData->isGoogleSignIn)
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

		if (strlen($jsonData->emailAddress) < 1) {
			sendResponse(400,false,"Email Address must not be empty");
		} else {
			if (!validateEmail($jsonData->emailAddress)) {
				sendResponse(400,false,"Invalid Email Address");
			}
		}

		if (strlen($jsonData->mobileNumber) != 11) {
			sendResponse(400,false,"Mobile Number must be 11 digits");
		}

		if (strlen($jsonData->address) < 1) {
			sendResponse(400,false,"Address must not be empty");
		}

		if ($jsonData->isGoogleSignIn == 0) {
			if (strlen($jsonData->username) < 1) {
				sendResponse(400,false,"Username must not be empty");
			} else if (strlen($jsonData->username) < 8) {
				sendResponse(400,false,"Username must be at least 8 characters");
			} else if (strlen($jsonData->username) > 255) {
				sendResponse(400,false,"Username is too long");
			}

			$has_specialChars = preg_match('@[^\w]@', $jsonData->username);

			if ($has_specialChars) {
				sendResponse(400,false,"Username must not have special characters");
			}

			if (strlen($jsonData->password) < 1 || strlen($jsonData->rPassword) < 1) {
				sendResponse(400,false,"Passwords must not be empty");
			}

			if ($jsonData->password != $jsonData->rPassword) {
				sendResponse(400,false,"Passwords are not the same");
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

			$username = trim($jsonData->username);
		
			try {
				$query = $writeDB->prepare("SELECT * FROM cf_registration WHERE IFNULL(username,'') = :username");
				$query->bindParam(':username',$username,PDO::PARAM_STR);
				$query->execute();

				$rowCount = $query->rowCount();

				if ($rowCount !== 0) {
					sendResponse(409,false,"Username already exist");
				}
			} catch (PDOException $ex) {
				error_log("Database query error: ".$ex,0);
				sendResponse(500,false,"There was an error creating account. Please try again");
			}
		} 


		$emailAddress = trim($jsonData->emailAddress);
		
		try {
			$query = $writeDB->prepare("SELECT * FROM cf_registration WHERE emailAddress = :emailAddress AND isAccountVerified = 1");
			$query->bindParam(':emailAddress',$emailAddress,PDO::PARAM_STR);
			$query->execute();

			$rowCount = $query->rowCount();

			if ($rowCount !== 0) {
				sendResponse(409,false,"Email address already exist");
			}
		} catch (PDOException $ex) {
			error_log("Database query error: ".$ex,0);
			sendResponse(500,false,"There was an error creating account. Please try again");
		}


		$password = trim($jsonData->password);
		$hashed_password = password_hash($password, PASSWORD_DEFAULT);

		try {
			$query = $writeDB->prepare("
				INSERT INTO cf_registration 
					(firstName,middleName,lastName,emailAddress,mobileNumber,address,password,isGoogleSignIn,isAccountVerified,username) 
				VALUES 
					(:firstName,:middleName,:lastName,:emailAddress,:mobileNumber,:address,:password,:isGoogleSignIn,0,:username)
			");
			$query->bindParam(':firstName',$jsonData->firstName,PDO::PARAM_STR);
			$query->bindParam(':middleName',$jsonData->middleName,PDO::PARAM_STR);
			$query->bindParam(':lastName',$jsonData->lastName,PDO::PARAM_STR);
			$query->bindParam(':emailAddress',$jsonData->emailAddress,PDO::PARAM_STR);
			$query->bindParam(':mobileNumber',$jsonData->mobileNumber,PDO::PARAM_STR);
			$query->bindParam(':address',$jsonData->address,PDO::PARAM_STR);
			$query->bindParam(':password',$hashed_password,PDO::PARAM_STR);
			$query->bindParam(':isGoogleSignIn',$jsonData->isGoogleSignIn,PDO::PARAM_STR);
			$query->bindParam(':username',$jsonData->username,PDO::PARAM_STR);
			$query->execute();

			$rowCount = $query->rowCount();

			if ($rowCount === 0) {
				sendResponse(500,false,"There was an issue creating your user account.Please try again");
			}

			sendResponse(201,true,"User account has been created. Go to your email for account verification");
		} catch (PDOException $ex) {
			error_log("Database query error: ".$ex,0);
			sendResponse(500,false,"There was an error creating account. Please try again ");
		}

	} else if ($method == 'PATCH') {
		if(!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] !== 'application/json') {
			sendResponse(400,false,"Content type header is not JSON");
		}

		$rawPOSTData = file_get_contents('php://input');

		if (!$jsonData = json_decode($rawPOSTData)) {
			sendResponse(400,false,"Request body is not JSON");
		}

		if (
			!isset($jsonData->emailAddress) || 
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


		$emailAddress = $jsonData->emailAddress;
		$hashed_password = password_hash($jsonData->password, PASSWORD_DEFAULT);

		$query = $writeDB->prepare('SELECT `password` FROM cf_registration WHERE emailAddress = :emailAddress AND isAccountVerified = 1 AND isGoogleSignIn = 0');
		$query->bindParam(':emailAddress',$emailAddress,PDO::PARAM_STR);
		$query->execute();

		$rowCount = $query->rowCount();

		if ($rowCount === 0) {
			sendResponse(404,false,"Account not found");	
		}

		$query = $writeDB->prepare("UPDATE cf_registration SET password = :password WHERE emailAddress = :emailAddress");
		$query->bindParam(':password',$hashed_password,PDO::PARAM_STR);
		$query->bindParam(':emailAddress',$emailAddress,PDO::PARAM_STR);
		$query->execute();

		$rowCount = $query->rowCount();

		if ($rowCount === 0) {
			sendResponse(500,false,"There was an issue changing your password.Please try again");
		}

		sendResponse(201,true,"Password has been updated. You can now login with your new credentials");

	} else if ($method == 'GET') {
		if ($_GET['command'] === 'otp') {
			$email = $_GET["email"];
			$otp = generateOTP();
			$arrEmailContent = array();


			if ($email === '') {
				sendResponse(400,false,"Email address cannot be blank");
			}

			if (strlen($email) < 1) {
				sendResponse(400,false,"Please provide an email address");
			}

			if (!validateEmail($email)) {
				sendResponse(400,false,"Invalid Email Address");
			}

			// try {
			// 	$query = $writeDB->prepare("SELECT * FROM cf_registration WHERE emailAddress = :emailAddress");
			// 	$query->bindParam(':emailAddress',$email,PDO::PARAM_STR);
			// 	$query->execute();

			// 	$rowCount = $query->rowCount();

			// 	if ($rowCount !== 0) {
			// 		sendResponse(409,false,"Unable to proceed.Your sending an OTP to an existing account");
			// 	}
			// } catch (PDOException $ex) {
			// 	error_log("Database query error: ".$ex,0);
			// 	sendResponse(500,false,"There was an error creating account. Please try again");
			// }

			try {
				$query = $writeDB->prepare("
					INSERT INTO cf_otp 
						(emailAddress,otp)
					VALUES
						(:emailAddress,:otp)
				");
				$query->bindParam(':emailAddress',$email,PDO::PARAM_STR);
				$query->bindParam(':otp',$otp,PDO::PARAM_STR);
				$query->execute();

				$rowCount = $query->rowCount();

				if ($rowCount === 0) {
					sendResponse(500,false,"There was an issue creating sending OTP.Please try again");
				}

				array_push($arrEmailContent,$email);
				array_push($arrEmailContent,$otp);

				sendResponse(201,true,"OTP has been sent to your email",$arrEmailContent,false);
			} catch (PDOException $ex) {
				error_log("Database query error: ".$ex,0);
				sendResponse(500,false,"There was an issue creating sending OTP.Please try again");
			}
		} else if ($_GET['command'] === 'verify') {
			$email = $_GET["email"];

			try {
				$query = $writeDB->prepare("UPDATE cf_registration SET isAccountVerified = 1 WHERE emailAddress = :emailAddress");
				$query->bindParam(':emailAddress',$email,PDO::PARAM_STR);
				$query->execute();

				$rowCount = $query->rowCount();

				if ($rowCount === 0) {
					sendResponse(500,false,"Unable to verify account. Please try again later");
				}

				sendResponse(201,true,"User account has been verified. You may now login your account");
			} catch (PDOException $ex) {
				error_log("Database query error: ".$ex,0);
				sendResponse(500,false,"There was an issue creating sending OTP.Please try again");
			}
		} else if ($_GET['command'] === 'gsignin') {
			$email = $_GET["email"];

			try {
				$query = $writeDB->prepare("SELECT * FROM cf_registration WHERE emailAddress = :emailAddress");
				$query->bindParam(':emailAddress',$email,PDO::PARAM_STR);
				$query->execute();

				$rowCount = $query->rowCount();

				if ($rowCount !== 0) {
					while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
						if ($row["isGoogleSignIn"] == 1) {
							if ($row["isAccountVerified"] == 1) {
								$returnData = array();
								$returnData['user_id'] = $row["id"];


								sendResponse(200,true,"PROCEED_TO_LOGIN",$returnData);
							} else {
								sendResponse(200,true,"PROCEED_TO_REGISTRATION");
							}
						} else {
							sendResponse(200,true,"PROCEED_TO_LOGIN_PASSWORD");
						}
					}
				} else {
					sendResponse(200,true,"PROCEED_TO_REGISTRATION");
				}
			} catch (PDOException $ex) {
				error_log("Database query error: ".$ex,0);
				sendResponse(500,false,"There was an error creating account. Please try again");
			}

		} else if ($_GET['command'] === 'profile') {
			$user_id = $_GET["user_id"];

			try {
				$query = $writeDB->prepare("SELECT * FROM cf_registration WHERE id = :id");
				$query->bindParam(':id',$user_id,PDO::PARAM_INT);
				$query->execute();
				$returnData = array();

				while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
					$returnData["firstName"]    = $row["firstName"];
					$returnData["middleName"]   = $row["middleName"];
					$returnData["lastName"]     = $row["lastName"];
					$returnData["emailAddress"] = $row["emailAddress"];
					$returnData["mobileNumber"] = $row["mobileNumber"];
					$returnData["address"]      = $row["address"];
					$returnData["username"]     = $row["username"];
					$returnData["imageLink"]    = $row["imageLink"] == "" ? "-" : $row["imageLink"];
				}

				sendResponse(201,true,"Account has been retreived",$returnData);
			} catch (PDOException $ex) {
				error_log("Database query error: ".$ex,0);
				sendResponse(500,false,"There was an error creating account. Please try again");
			}
		} else {
			sendResponse(400,false,"Endpoint not found");
		}
	} else {
		sendResponse(404,false,"Endpoint not found");
	}
?>