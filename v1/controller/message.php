<?php
	require_once('../helper/conn.php');
	require_once('../model/response.php');
	require_once('../helper/message.php');
	require_once('../helper/utils.php');
	require_once('../helper/date.php');

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

		if (
			!isset($jsonData->sender_id) || 
			!isset($jsonData->receiver_id) ||
			!isset($jsonData->message_text)
		) {
			sendResponse(400,false,"The JSON body you sent has incomplete parameters");
		}

		try {
			$query = $writeDB->prepare("
				INSERT INTO cf_message 
					(sender_id,receiver_id,message_text,timestamp) 
				VALUES 
					(:sender_id,:receiver_id,:message_text,:timestamp)
			");
			$query->bindParam(':sender_id',$jsonData->sender_id,PDO::PARAM_INT);
			$query->bindParam(':receiver_id',$jsonData->receiver_id,PDO::PARAM_INT);
			$query->bindParam(':message_text',$jsonData->message_text,PDO::PARAM_STR);
			$query->bindParam(':timestamp',$global_date,PDO::PARAM_STR);
			$query->execute();

			$rowCount = $query->rowCount();

			if ($rowCount === 0) {
				sendResponse(500,false,"There was an issue sending your message.Please try again");
			}

			sendResponse(201,true,"Message has been send completely");
		} catch (PDOException $ex) {
			error_log("Database query error: ".$ex,0);
			sendResponse(500,false,"There was an issue sending your message.Please try again");
		}
	} else if ($method === 'GET') {
		if ($_GET['command'] === 'my-chat') {
			$receiver_id = $_GET["receiver_id"];
			$sender_id = $_GET["sender_id"];
			$is_all = $_GET["is_all"];
			$queryLimit = "ASC";

			if ($is_all == 0) {
				$queryLimit = "DESC LIMIT 1";
			}

			$query = $writeDB->prepare("
				SELECT
					message_id AS id,
				    sender_id,
				    receiver_id,
				    message_text,
				    `timestamp`,
				    isRead
				FROM
				    cf_message
				WHERE
				    (sender_id = :s_id AND receiver_id = :r_id)
				    OR (sender_id = :rr_id AND receiver_id = :ss_id)
				ORDER BY
				    `timestamp` ".$queryLimit.";
			");
			$query->bindParam(':s_id',$sender_id,PDO::PARAM_INT);
			$query->bindParam(':r_id',$receiver_id,PDO::PARAM_INT);
			$query->bindParam(':ss_id',$sender_id,PDO::PARAM_INT);
			$query->bindParam(':rr_id',$receiver_id,PDO::PARAM_INT);
			$query->execute();
			$message = array();
			$arrID = array();

			while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
				$temp = array();
				$isMine = $row["sender_id"] == $sender_id ? 1 : 0;
				$temp["messageText"] = $row["message_text"];
				$temp["isMine"] = $isMine;
				$temp["isRead"] = $row["isRead"];

				if ($isMine != 1) {
					array_push($arrID, $row["id"]);
				}

				if ($is_all == 0) {
					if ($row["receiver_id"] == $receiver_id) {
						array_push($arrID, $row["id"]);
					}
				}
				$message[] = $temp;
			}

			if (count($arrID) != 0) {
				try {
					$query = $writeDB->prepare("
						UPDATE cf_message SET isRead = 1 WHERE message_id IN (".implode(', ', $arrID).") AND isRead = 0
					");
					$query->execute();

					$rowCount = $query->rowCount();
				} catch (PDOException $ex) {
					error_log("Database query error: ".$ex,0);
					sendResponse(500,false,"There was an issue reading your message.Please try again");
				}
			}

			$returnData = array();
			$returnData["rows_returned"] = count($message);
			$returnData["message"] = $message;

			sendResponse(201,true,"Message has been retreived",$returnData);
		} else if ($_GET['command'] === 'all-chat') {
			$id = $_GET["id"];
			$search = $_GET["search"];
			$query = "";

			if ($search != "~") {
				$query = "WHERE a.contact_name LIKE '%".$search."%'";
			}

			$query = $writeDB->prepare("
				SELECT 
					a.* 
				FROM (
					SELECT
					    proper(CONCAT(u.lastName,', ',u.firstName,' ',u.middleName)) AS contact_name,
					    m1.message_text AS last_message,
						u.imageLink,
						IF(m1.sender_id != ".$id.",m1.receiver_id,m1.sender_id) AS receiver_id,
						IF(m1.sender_id = ".$id.",m1.receiver_id,m1.sender_id) AS sender_id
					FROM
					    (
					        SELECT
					            CASE
					                WHEN sender_id = ".$id." THEN receiver_id
					                ELSE sender_id
					            END AS contact_id,
					            MAX(timestamp) AS max_timestamp
					        FROM
					            cf_message
					        WHERE
					            sender_id = ".$id." OR receiver_id = ".$id."
					        GROUP BY
					            contact_id
					    ) AS latest_messages
					JOIN
					    cf_registration AS u ON latest_messages.contact_id = u.id
					JOIN
					    cf_message AS m1 ON (
					        (m1.sender_id = ".$id." AND m1.receiver_id = u.id)
					        OR (m1.sender_id = u.id AND m1.receiver_id = ".$id.")
					    )
					    AND m1.timestamp = latest_messages.max_timestamp
					ORDER BY
					    latest_messages.max_timestamp DESC
				) a ".$query."
			");
			$query->execute();
			$message = array();

			while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
				$temp = array();
				$temp["contact_name"] = $row["contact_name"];
				$temp["last_message"] = $row["last_message"];
				$temp["imageLink"] = $row["imageLink"];
				$temp["receiver_id"] = $row["receiver_id"];
				$temp["sender_id"] = $row["sender_id"];
				$message[] = $temp;
			}

			$returnData = array();
			$returnData["rows_returned"] = count($message);
			$returnData["message"] = $message;

			sendResponse(201,true,"Message has been retreived",$returnData);
		}
	} else {
		sendResponse(404,false,"Endpoint not found");
	}
?>