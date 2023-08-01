<?php
    date_default_timezone_set('Asia/Manila');

    function validateEmail(string $email): bool {
        return preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $email);
    }

    function randomPassword() {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = array(); 
        $alphaLength = strlen($alphabet) - 1; 
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); 
    }

    function getDomainURL() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
        return $current_url = $protocol . $_SERVER['HTTP_HOST'];
    }

    function getCurrentDateAndTime() {
        return date('Y-m-d H:i:s', time());
    }

    function generateOTP() {
        $otp = "";
        $digits = "0123456789";
        $length = 6;

        for ($i = 0; $i < $length; $i++) {
            $otp .= $digits[rand(0, strlen($digits) - 1)];
        }

        return $otp;
    }

    // function checkAccess() {
    //     global $writeDB;
    //     $accesstoken     = $_SERVER["HTTP_AUTHORIZATION"];
    //     $returned_userid = 0;

    //     try {
    //         $query = $writeDB->prepare('
    //             SELECT 
    //                 a.userid,
    //                 a.accesstokenexpiry,
    //                 b.isActive,
    //                 b.loginattempts 
    //             FROM
    //                 yp_sessions a 
    //             INNER JOIN 
    //                 yp_mobile_registration b 
    //             ON 
    //                 a.userid = b.id 
    //             WHERE
    //                 a.accesstoken = :accesstoken
    //         ');
    //         $query->bindParam(':accesstoken',$accesstoken, PDO::PARAM_STR);
    //         $query->execute();

    //         $rowCount = $query->rowCount();

    //         if ($rowCount === 0) {
    //             sendResponse(401,false,"Invalid accesstoken");
    //         }

    //         $row = $query->fetch(PDO::FETCH_ASSOC);
    //         $returned_userid = $row["userid"];
    //         $returned_accesstokenexpiry = $row["accesstokenexpiry"];
    //         $returned_useractive = $row["isActive"];
    //         $returned_loginattempts = $row["loginattempts"];

    //         if ($returned_useractive !== 1) {
    //             sendResponse(402,false,"User account not active");
    //         }

    //         if ($returned_loginattempts >= 3) {
    //             sendResponse(403,false,"User account is currently locked out");
    //         }

    //         if (strtotime($returned_accesstokenexpiry) < time()) {
    //             sendResponse(404,false,"Access token expired");
    //         }
    //     } catch (PDOException $ex) {
    //         error_log("Database Query error - ".$ex,0);
    //         sendResponse(500,false,"There was an issue authenticating. Please try again");
    //     }

    //     return $returned_userid;
    // }

    function formatTimeAgo($dateString) {
        $timestamp = strtotime($dateString);
        $currentTimestamp = time();
        $difference = $currentTimestamp - $timestamp;
        
        $intervals = array(
            array('year', 31536000),
            array('month', 2592000),
            array('week', 604800),
            array('day', 86400),
            array('hour', 3600),
            array('minute', 60),
            array('second', 1)
        );
        
        foreach ($intervals as $interval) {
            $unit = $interval[0];
            $seconds = $interval[1];
            if ($difference >= $seconds) {
                $value = floor($difference / $seconds);
                $output = $value . ' ' . $unit;
                if ($value > 1) {
                    $output .= 's';
                }
                $output .= ' ago';
                return $output;
            }
        }
        
        return 'Just now';
    }
?>
