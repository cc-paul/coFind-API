<?php
		
	class DB {
		private static $writeDBConnection;
		private static $readDBConnection;
		private static $host = "localhost";
		private static $dbname = "cofind";
		private static $username = "root";
		private static $password = "";
		
		public static function connectionWriteDB() {
			if (self::$writeDBConnection === null) {
				self::$writeDBConnection = new PDO('mysql:host='.self::$host.';dbname='.self::$dbname.';chrset=utf8',self::$username,self::$password);
				self::$writeDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				self::$writeDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			}

			return self::$writeDBConnection;
		}

		public static function connectionReadDB() {
			if (self::$readDBConnection === null) {
				self::$readDBConnection = new PDO('mysql:host='.self::$host.';dbname='.self::$dbname.';chrset=utf8',self::$username,self::$password);
				self::$readDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				self::$readDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			}

			return self::$readDBConnection;
		}
	}

?>