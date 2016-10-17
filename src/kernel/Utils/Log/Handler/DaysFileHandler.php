<?php
namespace NetaServer\Utils\Log\Handler;

use \Monolog\Logger;
use \NetaServer\Injection\Container;

/**
 * 按日期分日志文件
 * */
class DaysFileHandler extends \Monolog\Handler\StreamHandler 
{
	protected $dateFormat = 'Y-m-d';
	
	protected $filenameFormat = '{filename}-{date}';
	
	//项目根目录
	private $rootPre = __ROOT__;
	
	private $separator = DIRECTORY_SEPARATOR;
	
	private $maxFiles = 180;//目录下日志文件最大数量。0为不限制
	
	private $fileManager;
	
	public function __construct($stream, $level = Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false) 
	{
		$this->fileManager = Container::getInstance()->make('file.manager');
		
		$this->filename = $this->rootPre . $stream;
		
		$this->removeExcessFile();//删除超出的文件
		
		$this->formatFilename($this->filename);
		
		parent::__construct($this->filename, $level, $bubble, $filePermission);
	}
	
	private function formatFilename(& $stream) 
	{
		if (! is_string($stream)) {
			return false;
		}
		$fileInfo = pathinfo($stream);
		$glob = str_replace(
				array('{filename}', '{date}'),
				array($fileInfo['filename'], date($this->dateFormat)),
				$this->filenameFormat
		);
		
		$stream = $fileInfo['dirname'] . $this->separator . $glob . '.' . $fileInfo['extension'];
	}
	
	public function getFileManager() 
	{
		return $this->fileManager;
	}
	
	public function setMaxFiles($maxFiles) 
	{
		$this->maxFiles = $maxFiles;
	}
	
	public function setDateFormat($format) 
	{
		$this->dateFormat = $format;
	}
	
	/**
	 * 删除多余的文件 
	 * */
    protected function removeExcessFile() 
    {
        if (0 === $this->maxFiles) {
            return; //unlimited number of files for 0
        }

        $filePattern = $this->getFilePattern();
        $dirPath = $this->getFileManager()->getDirName($this->filename);
        $logFiles = $this->getFileManager()->getFileList($dirPath, false, $filePattern, true);

        if (! empty( $logFiles) && count($logFiles) > $this->maxFiles) {

            usort($logFiles, function($a, $b) {
                return strcmp($b, $a);
            });

            $logFilesToBeRemoved = array_slice($logFiles, $this->maxFiles);

            $this->getFileManager()->removeFile($logFilesToBeRemoved, $dirPath);
        }
    }

    protected function getFilePattern() 
    {
        $fileInfo = pathinfo($this->filename);
        $glob = str_replace(
            array('{filename}', '{date}'),
            array($fileInfo['filename'], '.*'),
            $this->filenameFormat
        );

        if (! empty($fileInfo['extension'])) {
            $glob .= '\.' . $fileInfo['extension'];
        }

        $glob = '^' . $glob . '$';

        return $glob;
    }
}
