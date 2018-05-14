<?php
//namespace yourNamespace;
use DateTime;
use RuntimeException;
/**
 * Log class based on https://github.com/katzgrau/KLogger
 * maded by Kenny Katzgrau <katzgrau@gmail.com>
 *
 * @author  Mateusz Jaszkiewicz
 * @since   14.05.2018
 * @link    https://github.com/katzgrau/KLogger
 */
class Log
{

    protected static $options = array (
        'extension'      => 'txt',
        'dateFormat'     => 'Y-m-d G:i:s',
        'filename'       => false,
        'prefix'         => 'log_',
        'appendContext'  => true,
    );

    private static $logFilePath;

    /**
     * Current minimal log Threshold
     * @var integer
     */
    protected static $logLevelThreshold = 'success';

    protected static $logLevels = array(
      'off'     => 0,
      'access'  => 1,
      'error  ' => 2,
      'warning' => 3,
      'success' => 4
    );

    /**
     * Handle to a log file
     * @var resource
     */
    private static $fileHandle;

    private static $defaultPermissions = 0777;

    /**
     * Class constructor
     *
     * @param string $logDirectory      File path to the logging directory
     * @param string $logLevelThreshold The LogLevel Threshold
     * @param array  $options
     *
     * @internal param string $logFilePrefix The prefix for the log file name
     * @internal param string $logFileExt The extension for the log file
     */
    private static function prepare(array $options = array())
    {
        $logDirectory = \Config\Website\Config::$logdir;
        self::$options['filename'] = \Config\Website\Config::$logfile;
        self::$logLevelThreshold = \Config\Website\Config::$loglevel;
        self::$options = array_merge(self::$options, $options);
        $logDirectory = rtrim($logDirectory, DIRECTORY_SEPARATOR);
        if ( ! file_exists($logDirectory)) {
            mkdir($logDirectory, self::$defaultPermissions, true);
        }
        if(strpos($logDirectory, 'php://') === 0) {
            self::$logFilePath = $logDirectory;
            self::$setFileHandle('w+');
        } else {
            self::setLogFilePath($logDirectory);
            if(file_exists(self::$logFilePath) && !is_writable(self::$logFilePath)) {
                throw new RuntimeException('The file could not be written to. Check that appropriate permissions have been set.');
            }
            self::setFileHandle('a');
        }
        if ( ! self::$fileHandle) {
            throw new RuntimeException('The file could not be opened. Check permissions.');
        }
    }

    /**
     * @param string $logDirectory
     */
    private static function setLogFilePath($logDirectory) {
        if (self::$options['filename']) {
            if (strpos(self::$options['filename'], '.log') !== false || strpos(self::$options['filename'], '.txt') !== false) {
                self::$logFilePath = $logDirectory.DIRECTORY_SEPARATOR.self::$options['filename'];
            }
            else {
                self::$logFilePath = $logDirectory.DIRECTORY_SEPARATOR.self::$options['filename'].'.'.self::$options['extension'];
            }
        } else {
            self::$logFilePath = $logDirectory.DIRECTORY_SEPARATOR.self::$options['prefix'].date('m-Y').'.'.self::$options['extension'];
        }
    }

    /**
     * @param $writeMode
     *
     * @internal param resource $fileHandle
     */
    private static function setFileHandle($writeMode) {
        self::$fileHandle = fopen(self::$logFilePath, $writeMode);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @return null
     */
    public static function log($level, $message, array $context = array(), array $options = array())
    {
        if (self::$logLevels[self::$logLevelThreshold] < self::$logLevels[$level]) {
            return;
        }
        self::prepare($options);
        $message = self::formatMessage($level, $message, $context);
        self::write($message);
        fclose(self::$fileHandle);
    }

    /**
     * Writes a line to the log without prepending a status or timestamp
     *
     * @param string $message Line to write to the log
     * @return void
     */
    private static function write($message)
    {
        if (null !== self::$fileHandle) {
            if (fwrite(self::$fileHandle, $message) === false) {
                throw new RuntimeException('The file could not be written to. Check that appropriate permissions have been set.');
            }
        }
    }

    /**
     * Formats the message for logging.
     *
     * @param  string $level   The Log Level of the message
     * @param  string $message The message to log
     * @param  array  $context The context
     * @return string
     */
    private static function formatMessage($level, $message, $context)
    {
        $message = "[".(new DateTime())->format('Y-m-d G-i-s')."] [{$level}] {$message}";

        if (self::$options['appendContext'] && ! empty($context)) {
            $message .= "\t[".self::contextToString($context)."]";
        }
        return $message.PHP_EOL;
    }

    /**
     * Takes the given context and coverts it to a string.
     *
     * @param  array $context The Context
     * @return string
     */
    private static function contextToString($context)
    {
        $export = '';
        foreach ($context as $key => $value) {
            $export .= "{$key}: ";
            $export .= preg_replace(array(
                '/=>\s+([a-zA-Z])/im',
                '/array\(\s+\)/im',
                '/^  |\G  /m'
            ), array(
                '=> $1',
                'array()',
                '    '
            ), str_replace('array (', 'array(', var_export($value, true)));
            $export .= "\t";
        }
        return str_replace(array('\\\\', '\\\''), array('\\', '\''), rtrim($export));
    }
}
