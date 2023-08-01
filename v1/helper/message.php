<?php
	function sendResponse($statusCode,$success,$message = null,$data = null,$toCache = false) {
		$response = new Response();
		$response->setHttpStatusCode($statusCode);
		$response->setSuccess($success);
		
		if ($message != null || $message !== "") {
			$response->addMessage($message);
		}

		$response->toCache($toCache);

		if ($data != null) {
			$response->setData($data);
		}

		$response->send();
		exit;
	}
?>