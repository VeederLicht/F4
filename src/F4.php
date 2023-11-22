<?php

/** version 0.8.0  */

class LOGLEVEL
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

    /************************************************************************80
		GENERAL STUFF
		constructor, variables, generic functions etc.
     ************************************************************************/

    private $LogLevel;
    private $LogFile;
    public $aPublicErrors;  // error-info available for end-user

    //***********************************************************************
    // NOTE: to use named arguments, PHP8+ is required
    function __construct(string $logdir, string $logfile = 'f4log_', int $loglevel = 1, bool $debug = FALSE)
    {
        if ($debug) {
            /*	DISPLAY ERRORS */
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
        }
        if (!is_dir($logdir)) die('F4: Invalid logdir');
        $this->LogLevel = $loglevel;
        $this->LogFile = $logdir . $logfile . date('Ymd') . '.log';
        $this->aPublicErrors = [];
        $this->session();
    }

    //***********************************************************************
    // The Cookie Law is a piece of privacy legislation that requires websites to get consent from visitors to store or retrieve any information on a computer, smartphone or tablet.
    // There are other technologies, like Flash and HTML5 Local Storage that do similar things, and these are also covered by the legislation, but as cookies are the most common technology in use, it has become known as the Cookie Law.
    // Make sure your page has some cookie-option or notification
    private function session()
    {
        session_start();
    }

    //***********************************************************************
    public function Log(string $msg, string $type, bool $public = FALSE)
    {
        if ($type <= $this->LogLevel) {      // check loglevel
            $entry = date("Ymd_H:i:s") . LOGLEVEL::getMsg($type) . $msg . PHP_EOL;
            if (file_put_contents($this->LogFile, $entry, FILE_APPEND) === false)
                $this->aPublicErrors[] = "Log() [ERROR] » Cannot write log file, check server logs for details!";
            if ($public)
                $this->aPublicErrors[] = $msg;
        }
    }

    //***********************************************************************
    public function printArray($array)
    {
        print("<pre>" . print_r($array, true) . "</pre>");
    }

    //***********************************************************************
    public function printSysInfo()
    {
        echo "<hr>";
        echo "<h4>PHP MEMORY USAGE:<h4/>";
        echo "<h5>memory_get_usage(false): <h5/>" . memory_get_usage(false);
        echo "<h5>memory_get_usage(true): <h5/>" . memory_get_usage(true);
        echo "<h5>memory_get_peak_usage(false): <h5/>" . memory_get_peak_usage(false);
        echo "<h5>memory_get_peak_usage(true): <h5/>" . memory_get_peak_usage(true);

        echo "<hr>";
        echo "<h4>REQUEST INFO:<h4/>";
        echo "<h5>REQUEST_URI: <h5/>" . $_SERVER['REQUEST_URI'];
        echo "<h5>QUERY_STRING: <h5/>" . $_SERVER['QUERY_STRING'];
    }


    /************************************************************************80
		MYSQL STUFF
		connect, query etc.
     ************************************************************************/
    public function DB_mysqli_connect(string $dbname)
    {
        try {
            /*
                    Wrap the rest of your code in the 'try' block
                    since any step in here can go wrong, and you
                    will be able to catch any exceptions. ***/

            // print_r(PDO::getAvailableDrivers());

            $dsn           = 'mysql:host=localhost;dbname=' . $dbname;
            $uname      = 'mysql';
            $pwd          = '0000';
            $db = new PDO($dsn, $uname, $pwd);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->Log("PDO exception: " . $e->getMessage(), LOGLEVEL::ERROR);
            return false;
        }
        return $db;
    }


    /************************************************************************80
		SQLITE STUFF
		connect, query etc.
     ************************************************************************/
    //***********************************************************************
    public function DB_sqlite_connect(string $dbname)
    {
        try {
            $db = new PDO("sqlite:$dbname");
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->Log("PDO exception: " . $e->getMessage(), LOGLEVEL::ERROR);
            return false;
        }
        return $db;
    }
    //***********************************************************************
    public function DB_sqlite_execute(string $dbname, string $query, array $bindings = []): PDOStatement
    {
        $db = $this->DB_sqlite_connect($dbname);
        $statement = $db->prepare($query);
        // array(  ':userInput' => $_POST['userInput']  )
        $result = $statement->execute($bindings);
        if ($result) {
            return $statement;
        } else {
            return false;
        }
    }


    /************************************************************************80
		SANITIZATION & SECURITY STUFF
		read:
        - https://www.phptutorial.net/php-tutorial/php-sanitize-input/
        - https://stackoverflow.com/questions/17166905/should-i-use-htmlspecialchars-or-mysql-real-escape-string-or-both
        - 

     ************************************************************************/
    //***********************************************************************
    // Use htmlspecialchars to protect against XSS attacks when you insert the data into an HTML document. Databases aren't HTML documents. (You might later take the data out of the database to put it into an HTML document, that is the time to use htmlspecialchars).
    public function output($text)
    {
        echo htmlspecialchars($text);
    }

    //***********************************************************************
    public function set_csrf()
    {
        if (!isset($_SESSION["csrf"])) {
            $_SESSION["csrf"] = bin2hex(random_bytes(50));
        }
        echo '<input type="hidden" name="csrf" value="' . $_SESSION["csrf"] . '">';
    }

    //***********************************************************************
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
		ROUTING STUFF
		routing, url-manipulation etc.
     ************************************************************************/
    public function route($routeArray)
    {
        $request_url = $this->getNormalizedURL();
        if (!filter_var($_SERVER['SCRIPT_URI'], FILTER_VALIDATE_URL)) {
            $this->serveError('400', $routeArray);
        } elseif (!array_key_exists($request_url, $routeArray)) {
            $this->serveError('404', $routeArray);
        } else {   // desired 
            $this->servePage( $routeArray[$request_url],  $routeArray);
        }
    }

    public function getNormalizedURL()
    {
        $url_out = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
        $url_out = rtrim($url_out, " \n\r\t\v\0\\");
        return $url_out;
        // moet nog de url zo filteren dat lege // eruit worden gehaald, zoals SCRIPT_URI maar dan voor elke server geldig?
    }

    public function serveError(string $err, array $routeArray = [])
    {
        if ($routeArray != [] && array_key_exists($err, $routeArray)) {   // if a custom template has been defined
            $this->servePage($routeArray[$err]);
        } else {
            http_response_code($err);
            echo "<h1>HTTP ERROR: $err</h1>";
        }
        exit();
    }

    public function servePage($page,  $routeArray = [])
    {
        if(!file_exists($page)) $this->serveError(501, $routeArray);
        require_once($page);
        exit;
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
