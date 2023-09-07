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

		if (!isset($jsonData->command)) {
			sendResponse(400,false,"The JSON body you sent has incomplete parameters");
		}

		$command = $jsonData->command;

		if ($command === "SAVE_JOB") {
			if (
				!isset($jsonData->id) || 
				!isset($jsonData->jobTitle) || 
				!isset($jsonData->description) || 
				!isset($jsonData->requirementsList) || 
				!isset($jsonData->jobType) || 
				!isset($jsonData->additionalInfo) || 
				!isset($jsonData->salary) || 
				!isset($jsonData->forDiscussion) || 
				!isset($jsonData->imageList) || 
				!isset($jsonData->status) ||
				!isset($jsonData->createdBy)
			) {
				sendResponse(400,false,"The JSON body you sent has incomplete parameters");
			}

			try {
				$query = $writeDB->prepare("
					INSERT INTO cf_jobs 
						(jobTitle,description,requirementsList,jobType,additionalInfo,salary,forDiscussion,imageList,status,createdBy,dateCreated) 
					VALUES 
						(:jobTitle,:description,:requirementsList,:jobType,:additionalInfo,:salary,:forDiscussion,:imageList,:status,:createdBy,:dateCreated)
				");
				$query->bindParam(':jobTitle',$jsonData->jobTitle,PDO::PARAM_STR);
				$query->bindParam(':description',$jsonData->description,PDO::PARAM_STR);
				$query->bindParam(':requirementsList',$jsonData->requirementsList,PDO::PARAM_STR);
				$query->bindParam(':jobType',$jsonData->jobType,PDO::PARAM_STR);
				$query->bindParam(':additionalInfo',$jsonData->additionalInfo,PDO::PARAM_STR);
				$query->bindParam(':salary',$jsonData->salary,PDO::PARAM_STR);
				$query->bindParam(':forDiscussion',$jsonData->forDiscussion,PDO::PARAM_INT);
				$query->bindParam(':imageList',$jsonData->imageList,PDO::PARAM_STR);
				$query->bindParam(':status',$jsonData->status,PDO::PARAM_STR);
				$query->bindParam(':createdBy',$jsonData->createdBy,PDO::PARAM_INT);
				$query->bindParam(':dateCreated',$global_date,PDO::PARAM_STR);
				$query->execute();

				$rowCount = $query->rowCount();

				if ($rowCount === 0) {
					sendResponse(500,false,"There was an issue creating your job.Please try again");
				}

				sendResponse(201,true,"Job has been created completely");
			} catch (PDOException $ex) {
				error_log("Database query error: ".$ex,0);
				sendResponse(500,false,"There was an error creating jobs. Please try again ");
			}
		} else if ($command === "UPDATE_JOB") {
			if (
				!isset($jsonData->id) || 
				!isset($jsonData->jobTitle) || 
				!isset($jsonData->description) || 
				!isset($jsonData->requirementsList) || 
				!isset($jsonData->jobType) || 
				!isset($jsonData->additionalInfo) || 
				!isset($jsonData->salary) || 
				!isset($jsonData->forDiscussion) || 
				!isset($jsonData->imageList) || 
				!isset($jsonData->status) ||
				!isset($jsonData->createdBy)
			) {
				sendResponse(400,false,"The JSON body you sent has incomplete parameters");
			}

			try {
				$query = $writeDB->prepare("
					UPDATE cf_jobs SET 
						jobTitle=:jobTitle,description=:description,requirementsList=:requirementsList,jobType=:jobType,
						additionalInfo=:additionalInfo,salary=:salary,forDiscussion=:forDiscussion,imageList=:imageList
					WHERE
						id = :id
				");
				$query->bindParam(':jobTitle',$jsonData->jobTitle,PDO::PARAM_STR);
				$query->bindParam(':description',$jsonData->description,PDO::PARAM_STR);
				$query->bindParam(':requirementsList',$jsonData->requirementsList,PDO::PARAM_STR);
				$query->bindParam(':jobType',$jsonData->jobType,PDO::PARAM_STR);
				$query->bindParam(':additionalInfo',$jsonData->additionalInfo,PDO::PARAM_STR);
				$query->bindParam(':salary',$jsonData->salary,PDO::PARAM_STR);
				$query->bindParam(':forDiscussion',$jsonData->forDiscussion,PDO::PARAM_INT);
				$query->bindParam(':imageList',$jsonData->imageList,PDO::PARAM_STR);
				$query->bindParam(':id',$jsonData->id,PDO::PARAM_INT);
				$query->execute();

				$rowCount = $query->rowCount();

				if ($rowCount === 0) {
					sendResponse(500,false,"There was an issue updating your job.Please try again");
				}

				sendResponse(201,true,"Job has been updated completely");
			} catch (PDOException $ex) {
				error_log("Database query error: ".$ex,0);
				sendResponse(500,false,"There was an error creating jobs. Please try again ");
			}
		} else if ($command === "SEARCH_POST") {
			if (!isset($jsonData->jobTitle) || !isset($jsonData->createdBy)) {
				sendResponse(400,false,"The JSON body you sent has incomplete parameters");
			}

			try {
				$query = $writeDB->prepare("
					SELECT
						a.*,
						b.address
					FROM
						cf_jobs a
					INNER JOIN
						cf_registration b 
					ON 
						a.createdBy = b.id 
					WHERE 
						b.id = :id 
					AND 
						a.jobTitle LIKE '%".$jsonData->jobTitle."%'
					ORDER BY 
						a.dateCreated DESC;
				");
				$query->bindParam(':id',$jsonData->createdBy,PDO::PARAM_INT);
				$query->execute();
				$jobs = array();

				while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
					$temp = array();
					$temp["id"]  = $row["id"];
					$temp["jobTitle"]  = $row["jobTitle"];
					$temp["description"]  = $row["description"];
					$temp["requirementsList"]  = $row["requirementsList"];
					$temp["jobType"]  = $row["jobType"];
					$temp["additionalInfo"]  = $row["additionalInfo"];
					$temp["salary"]  = $row["salary"];
					$temp["forDiscussion"]  = $row["forDiscussion"];
					$temp["imageList"]  = $row["imageList"];
					$temp["status"]  = $row["status"];
					$temp["createdBy"]  = $row["createdBy"];
					$temp["isActive"]  = $row["isActive"];
					$temp["dateCreated"]  = $row["dateCreated"];
					$temp["f_dateCreated"]  = "Posted ".formatTimeAgo($row["dateCreated"]);
					$temp["address"]  =  $row["address"];
					$jobs[] = $temp;
				}

				$returnData = array();
				$returnData["rows_returned"] = count($jobs);
				$returnData["jobs"] = $jobs;

				sendResponse(201,true,"Job has been retreived",$returnData);
			} catch (PDOException $ex) {
				error_log("Database query error: ".$ex,0);
				sendResponse(500,false,"There was an error getting jobs. Please try again ");
			}
		} else if ($command === "APPLY_JOB") {
			if (!isset($jsonData->id) || !isset($jsonData->applicantId)) {
				sendResponse(400,false,"The JSON body you sent has incomplete parameters");
			}


			try {
				$query = $writeDB->prepare("
					INSERT INTO cf_applied_job 
						(jobID,applicantID) 
					VALUES 
						(:jobID,:applicantID) 
				");
				$query->bindParam(':jobID',$jsonData->id,PDO::PARAM_INT);
				$query->bindParam(':applicantID',$jsonData->applicantId,PDO::PARAM_INT);
				$query->execute();

				$rowCount = $query->rowCount();

				if ($rowCount === 0) {
					sendResponse(500,false,"There was an issue applying job.Please try again");
				}

				sendResponse(201,true,"Job has been applied. You can chat the recruiter anytime or wait for his/her feedback");
			} catch (PDOException $ex) {
				error_log("Database query error: ".$ex,0);
				sendResponse(500,false,"There was an error applying jobs. Please try again ");
			}
		} else {
			sendResponse(404,false,"Command not found");
		}
	} else if ($method === 'GET') {
		if ($_GET['command'] === 'job') {
			$id = $_GET["id"];

			try {
				$query = $writeDB->prepare("
					SELECT
						a.*,
						b.address
					FROM
						cf_jobs a
					INNER JOIN
						cf_registration b 
					ON 
						a.createdBy = b.id 
					WHERE 
						a.id = :id 
					AND 
						a.isActive = 1
					ORDER BY 
						a.dateCreated DESC;
				");
				$query->bindParam(':id',$id,PDO::PARAM_INT);
				$query->execute();
				$jobs = array();

				while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
					$temp = array();
					$temp["id"]  = $row["id"];
					$temp["jobTitle"]  = $row["jobTitle"];
					$temp["description"]  = $row["description"];
					$temp["requirementsList"]  = $row["requirementsList"];
					$temp["jobType"]  = $row["jobType"];
					$temp["additionalInfo"]  = $row["additionalInfo"];
					$temp["salary"]  = $row["salary"];
					$temp["forDiscussion"]  = $row["forDiscussion"];
					$temp["imageList"]  = $row["imageList"];
					$temp["status"]  = $row["status"];
					$temp["createdBy"]  = $row["createdBy"];
					$temp["isActive"]  = $row["isActive"];
					$temp["dateCreated"]  = $row["dateCreated"];
					$temp["f_dateCreated"]  = "Posted ".formatTimeAgo($row["dateCreated"]);
					$temp["address"]  =  $row["address"];
					$jobs[] = $temp;
				}

				$returnData = array();
				$returnData["rows_returned"] = count($jobs);
				$returnData["jobs"] = $jobs;

				sendResponse(201,true,"Job has been retreived",$returnData);
			} catch (PDOException $ex) {
				error_log("Database query error: ".$ex,0);
				sendResponse(500,false,"There was an error getting jobs. Please try again ");
			}
		} else if ($_GET['command'] === 'job-all') { 
			$id = $_GET["id"];

			try {
				$query = $writeDB->prepare("
					SELECT 
						a.id,
						a.jobTitle,
						a.description,
						a.requirementsList,
						a.jobType,
						a.additionalInfo,
						IF(a.forDiscussion = 1,'Salary for Discussion',REPLACE(FORMAT(a.salary,2),'.00','')) AS salary,
						a.imageList,
						a.`status`,
						a.dateCreated,
						b.id AS createdBy,
						proper(CONCAT(b.lastName,', ',b.firstName,' ',IFNULL(b.middleName,''))) AS fullName,
						IFNULL(b.imageLink,'-') AS imageLink,
						IF(IFNULL(c.applicantId,0) = ".$id.",0,1) AS enableApplyButton
					FROM
						cf_jobs a 
					INNER JOIN
						cf_registration b 
					ON 
						a.createdBy = b.id 
					LEFT JOIN
						cf_applied_job c 
					ON 
						a.id = c.jobId
					WHERE
						a.isActive = 1
					ORDER BY
						a.dateCreated DESC;
				");
				$query->execute();
				$jobs = array();

				while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
					$temp = array();
					$temp["id"]  = $row["id"];
					$temp["jobTitle"]  = $row["jobTitle"];
					$temp["description"]  = $row["description"];
					$temp["requirementsList"]  = $row["requirementsList"];
					$temp["jobType"]  = $row["jobType"];
					$temp["additionalInfo"]  = $row["additionalInfo"];
					$temp["salary"]  = $row["salary"];
					$temp["imageList"]  = $row["imageList"];
					$temp["status"]  = $row["status"];
					$temp["dateCreated"]  = $row["dateCreated"];
					$temp["f_dateCreated"]  = "Posted ".formatTimeAgo($row["dateCreated"]);
					$temp["createdBy"]  =  $row["createdBy"];
					$temp["fullName"]  =  $row["fullName"];
					$temp["imageLink"] = $row["imageLink"];
					$temp["enableApplyButton"] = $row["enableApplyButton"];
					$jobs[] = $temp;
				}

				$returnData = array();
				$returnData["rows_returned"] = count($jobs);
				$returnData["jobs"] = $jobs;

				sendResponse(201,true,"Job has been retreived",$returnData);
			} catch (PDOException $ex) {
				error_log("Database query error: ".$ex,0);
				sendResponse(500,false,"There was an error getting jobs. Please try again ");
			}
		} else if ($_GET['command'] === 'delete_job') { 
			$id = $_GET["id"];

			try {
				$query = $writeDB->prepare("
					UPDATE cf_jobs SET 
						isActive = 0
					WHERE
						id = :id
				");
				$query->bindParam(':id',$id,PDO::PARAM_INT);
				$query->execute();

				$rowCount = $query->rowCount();

				if ($rowCount === 0) {
					sendResponse(500,false,"There was an issue deleting your job.Please try again");
				}

				sendResponse(201,true,"Job has been deleted");
			} catch (PDOException $ex) {
				error_log("Database query error: ".$ex,0);
				sendResponse(500,false,"There was an error deleting jobs. Please try again ");
			}
		}
	} else if ($method === 'DELETE') {
		
	} else {
		sendResponse(404,false,"Endpoint not found");
	}
?>