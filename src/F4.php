<?php
/** version 0.7.0  */
session_start();

class LOGTYPE
{
    const ERROR = 1;
    const WARN = 2;
    const INFO = 3;

    public static function getMsg(int $i)
    {
        switch ($i) {
            case 1:
                return " [ERROR] » ";
            case 2:
                return " [WARN] » ";
            case 3:
                return " [INFO] » ";
            default:
                return " [----] » ";
        }
    }
}


class F4
{

    private $LogLevel;
    private $LogFile;
    public $aPublicErrors;  // error-info available for end-user
    
    function __construct(int $level, $logdir)
    {
        $this->LogLevel = $level;
        $this->LogFile = $logdir .'f4log_' . date('Ymd') . '.log';
        $this->aPublicErrors = [];
    }


    /************************************************************************80
		METHOD DESCRIPTION:
		This function should ...
	*/
    public function Log(string $msg, string $type, bool $public = FALSE)
    {
        if ($type <= $this->LogLevel) {      // check loglevel
            $entry = date("Ymd_H:i:s") . LOGTYPE::getMsg($type) . $msg . PHP_EOL;
            if (file_put_contents($this->LogFile, $entry, FILE_APPEND) === false)
                $this->aPublicErrors[] = "Log() [ERROR] » Cannot write log file, check server logs for details!";
            if ($public)
                $this->aPublicErrors[] = $msg;
        }
    }


    /************************************************************************80
		METHOD DESCRIPTION:
		connect to a mysql/mariadb server

        NOG NIET GETEST
	*/
    public function DB_mysqli_connect(string $dbname)
    {
        try {
            /*
                    Wrap the rest of your code in the 'try' block
                    since any step in here can go wrong, and you
                    will be able to catch any exceptions. ***/

            // print_r(PDO::getAvailableDrivers());

            $uname      = 'mysql';
            $pwd          = '0000';
            $dsn           = 'mysql:host=localhost;dbname=' . $dbname;
            $db = new PDO($dsn, $uname, $pwd);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $db;
        } catch (PDOException $e) {
            $this->Log("PDO exception: " . $e->getMessage(), LOGTYPE::ERROR);
        }
    }

        /************************************************************************80
		METHOD DESCRIPTION:
		connect to a mysql/mariadb server

        NOG NIET GETEST
	*/
    public function DB_sqlite_connect(string $dbname)
    {
        try {
            $db = new PDO("sqlite:$dbname");
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $db;
        } catch (PDOException $e) {
            $this->Log("PDO exception: " . $e->getMessage(), LOGTYPE::ERROR);
        }
    }

    /************************************************************************80
		METHOD DESCRIPTION:
		This function should ...
	*/
    public function out($text)
    {
        echo htmlspecialchars($text);
    }

    /************************************************************************80
		METHOD DESCRIPTION:
		csrf setter & checker
	*/
    public function set_csrf()
    {
        if (!isset($_SESSION["csrf"])) {
            $_SESSION["csrf"] = bin2hex(random_bytes(50));
        }
        echo '<input type="hidden" name="csrf" value="' . $_SESSION["csrf"] . '">';
    }
    public function is_csrf_valid()
    {
        if (!isset($_SESSION['csrf']) || !isset($_POST['csrf'])) {
            return false;
        }
        if ($_SESSION['csrf'] != $_POST['csrf']) {
            return false;
        }
        return true;
    }

    /************************************************************************80
		METHOD DESCRIPTION:
		output current memory usage
	*/
    public function MemUsage()
    {
        echo "<h4>PHP MEMORY USAGE:<h4/>";
        echo "<h5>memory_get_usage(false): <h5/>" . memory_get_usage(false);
        echo "<h5>memory_get_usage(true): <h5/>" . memory_get_usage(true);
        echo "<h5>memory_get_peak_usage(false): <h5/>" . memory_get_peak_usage(false);
        echo "<h5>memory_get_peak_usage(true): <h5/>" . memory_get_peak_usage(true);
    }

    /************************************************************************80
		METHOD DESCRIPTION:
		output request info
	*/
    public function RequestInfo()
    {
        echo "<h4>REQUEST INFO:<h4/>";
        echo "<h5>REQUEST_URI: <h5/>" . $_SERVER['REQUEST_URI'];
        echo "<h5>QUERY_STRING: <h5/>" . $_SERVER['QUERY_STRING'];
    }



    /************************************************************************80
		METHOD DESCRIPTION:
		adapted from PHPRouter

        MOMENTEEL NIET EFFICIENT! VOOR ELKE REQUEST&ROUTE WORDT DEZE FUNCTIE UITGEVOERD TOTDAT DE JUISTE IS GEVONDEN
	*/
    public function route($route, $path_to_include)
    {
        $callback = $path_to_include;
        if (!is_callable($callback)) {
            if (!strpos($path_to_include, '.php')) {
            }
        }
        if ($route == "/404") {
            require_once $path_to_include;
            exit();
        }
        $request_url = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
        $request_url = rtrim($request_url, '/');
        $request_url = strtok($request_url, '?');
        $route_parts = explode('/', $route);
        $request_url_parts = explode('/', $request_url);
        array_shift($route_parts);
        array_shift($request_url_parts);
        if ($route_parts[0] == '' && count($request_url_parts) == 0) {
            // Callback function
            if (is_callable($callback)) {
                call_user_func_array($callback, []);
                exit();
            }
            require_once $path_to_include;
            exit();
        }
        if (count($route_parts) != count($request_url_parts)) {
            return;
        }
        $parameters = [];
        for ($__i__ = 0; $__i__ < count($route_parts); $__i__++) {
            $route_part = $route_parts[$__i__];
            if (preg_match("/^[$]/", $route_part)) {
                $route_part = ltrim($route_part, '$');
                array_push($parameters, $request_url_parts[$__i__]);
                $$route_part = $request_url_parts[$__i__];
            } else if ($route_parts[$__i__] != $request_url_parts[$__i__]) {
                return;
            }
        }
        // Callback function
        if (is_callable($callback)) {
            call_user_func_array($callback, $parameters);
            exit();
        }
        require_once $path_to_include;
        exit();
    }
}


// SERVEER STATISCHE BESTANDEN:
// (https://idiallo.com/blog/making-php-as-fast-as-nginx-or-apache)

// OPTIE 1
// $file_path = "/non/web/facing/file.csv"
// if ($user->isAuthorized()){
//     if (file_exists($file_path)){
//         header("Content-Type: text/plain");
//         readfile($file); // Reading the file into the output buffer
//         exit;

//     }else {
//         throw Error("File does not exist");
//         exit;
//     }
// }

// OPTIE 2 (apache module nodig)
// $file_path = "/non/web/facing/file.csv"
// if ($user->isAuthorized()){
//     if (file_exists($file_path)){
//         header ('X-Sendfile: ' . $file_path);
//         header("Content-Type: text/plain");
//         header('Content-Disposition: attachment; filename="file.csv"');
//         exit;

//     }else {
//         throw Error("File does not exist");
//         exit;
//     }
// }
