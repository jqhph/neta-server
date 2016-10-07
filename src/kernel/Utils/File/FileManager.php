<?php
namespace JQH\Utils\File;

use \JQH\Config\Config;
use JQH\Exceptions\Error;

class FileManager 
{
	private $root = __ROOT__;
	
	private $projectRoot = 'application';
	/**
	 * 权限控制对象实例
	 * */
	private $permission;
	
	private $separator = DIRECTORY_SEPARATOR;

    private $permissionDeniedList = [];

    public function __construct(Config $config = null) 
    {
        $params = null;
        
        if (isset($config)) {
            $params = array(
                'defaultPermissions' => $config->get('defaultPermissions'),
                'permissionMap' => $config->get('permissionMap'),
            );
        }

        //$this->permission = new Permission($this, $params);
    }
    
    // //检查类是否存在
    // public function classExists($className) 
    // {
    // 	$className = str_replace('\\', $this->separator, $className);
    // 	$path = $this->root . $this->projectRoot . $className . '.php';
    // 	if (is_file($path)) {
    // 		return true;
    // 	}
    // 	return false;
    // }
    

//     public function getPermissionUtils()
//     {
//         return $this->permission;
//     }

    /**
     * Get a list of files in specified directory
     *
     * @param string $path string - Folder path, Ex. myfolder
     * @param bool | int $recursively - Find files in subfolders
     * @param string $filter - Filter for files. Use regular expression, Ex. \.json$
     * @param bool $onlyFileType [null, true, false] - Filter for type of files/directories. If TRUE - returns only file list, if FALSE - only directory list
     * @param bool $isReturnSingleArray - if need to return a single array of file list
     *
     * @return array
     */
    public function getFileList($path, $recursively = false, $filter = '', $onlyFileType = null, $isReturnSingleArray = false)
    {
        $path = $this->concatPaths($path);

        $result = array();

        if (! is_file($path) || ! is_dir($path)) {
            return $result;
        }

        $cdir = scandir($path);
        foreach ($cdir as $key => $value) {
            if (!in_array($value,array(".", ".."))) {
                $add = false;
                if (is_dir($path . $this->separator . $value)) {
                    if ($recursively || (is_int($recursively) && $recursively!=0) ) {
                        $nextRecursively = is_int($recursively) ? ($recursively-1) : $recursively;
                        $result[$value] = $this->getFileList($path . $this->separator . $value, $nextRecursively, $filter, $onlyFileType);
                    }
                    else if (!isset($onlyFileType) || !$onlyFileType){ /*save only directories*/
                        $add = true;
                    }
                }
                else if (!isset($onlyFileType) || $onlyFileType) { /*save only files*/
                    $add = true;
                }

                if ($add) {
                    if (!empty($filter)) {
                        if (preg_match('/'.$filter.'/i', $value)) {
                            $result[] = $value;
                        }
                    }
                    else {
                        $result[] = $value;
                    }
                }

            }
        }

        if ($isReturnSingleArray) {
            return $this->getSingeFileList($result, $onlyFileType);
        }

        return $result;
    }

    /**
     * Convert file list to a single array
     *
     * @param aray $fileList
     * @param bool $onlyFileType [null, true, false] - Filter for type of files/directories.
     * @param string $parentDirName
     *
     * @return aray
     */
    protected function getSingeFileList(array $fileList, $onlyFileType = null, $parentDirName = '')
    {
        $singleFileList = array();
        foreach($fileList as $dirName => $fileName) {

            if (is_array($fileName)) {
                $currentDir = $this->concatPath($parentDirName, $dirName);

                if (!isset($onlyFileType) || $onlyFileType == $this->isFile($currentDir)) {
                    $singleFileList[] = $currentDir;
                }

                $singleFileList = array_merge($singleFileList, $this->getSingeFileList($fileName, $onlyFileType, $currentDir));

            } else {
                $currentFileName = $this->concatPath($parentDirName, $fileName);

                if (!isset($onlyFileType) || $onlyFileType == $this->isFile($currentFileName)) {
                    $singleFileList[] = $currentFileName;
                }
            }
        }

        return $singleFileList;
    }
    
    public function concatPath( $folderPath, $filePath = null) 
    {
    	if (is_array( $folderPath)) {
    		$fullPath = '';
    		foreach ($folderPath as $path) {
    			$fullPath = $this->concatPath($fullPath, $path);
    		}
    		return $this->fixPath($fullPath);
    	}
    
    	if (empty($filePath)) {
    		return $this->fixPath($folderPath);
    	}
    	if (empty($folderPath)) {
    		return $this->fixPath($filePath);
    	}
    
    	if (substr($folderPath, -1) == $this->separator || substr($folderPath, -1) == '/') {
    		return $this->fixPath($folderPath . $filePath);
    	}
    	return $folderPath . $this->separator . $filePath;
    }
    
    public function fixPath($path) 
    {
    	return str_replace('/', $this->separator, $path);
    }

    /**
     * Reads entire file into a string
     *
     * @param  string | array  $path  Ex. 'path.php' OR array('dir', 'path.php')
     * @param  boolean $useIncludePath
     * @param  resource  $context
     * @param  integer $offset
     * @param  integer $maxlen
     * @return mixed
     */
    public function getContents($path, $useIncludePath = false, $context = null, $offset = -1, $maxlen = null)
    {
        $fullPath = $this->concatPaths($path);

        if (is_file($fullPath)) {
            if (isset($maxlen)) {
                return file_get_contents($fullPath, $useIncludePath, $context, $offset, $maxlen);
            } else {
                return file_get_contents($fullPath, $useIncludePath, $context, $offset);
            }
        }

        return false;
    }

    /**
     * Get PHP array from PHP file
     *
     * @param  string | array $path
     * @return array | bool
     */
    public function getPhpContents($path)
    {
        $fullPath = $this->concatPaths($path);

        if (is_file($fullPath) && strtolower(substr($fullPath, -4)) == '.php') {
            $phpContents = include($fullPath);
            if (is_array($phpContents)) {
                return $phpContents;
            }
        }

        return false;
    }

    /**
     * Write data to a file
     *
     * @param  string | array  $path
     * @param  mixed  $data
     * @param  integer $flags
     * @param  resource  $context
     *
     * @return bool
     */
    public function putContents($path, $data, $flags = 0, $context = null)
    {
        $fullPath = $this->concatPaths($path); //todo remove after changing the params

//         if ($this->checkCreateFile($fullPath) === false) {
//             throw new Error('Permission denied for '. $fullPath);
//         }

        $res = (file_put_contents($fullPath, $data, $flags, $context) !== FALSE);
        if ($res && function_exists('opcache_invalidate')) {
            opcache_invalidate($fullPath);
        }

        return $res;
    }

    /**
     * Save PHP content to file
     *
     * @param string | array $path
     * @param string $data
     *
     * @return bool
     */
    public function putPhpContents($path, $data)
    {
        return $this->putContents($path, $this->getPHPFormat($data), LOCK_EX);
    }

    /**
     * Save JSON content to file
     *
     * @param string | array $path
     * @param string $data
     * @param  integer $flags
     * @param  resource  $context
     *
     * @return bool
     */
    public function putContentsJson($path, $data)
    {
        if( is_array($data)) {
            $data = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return $this->putContents($path, $data, LOCK_EX);
    }



    /**
     * Merge PHP content and save it to a file
     *
     * @param string | array $path
     * @param string $content JSON string
     * @param string | array $removeOptions - List of unset keys from content
     * @return bool
     */
    public function mergePhpContents($path, $content, $removeOptions = null)
    {
        return $this->mergeContents($path, $content, false, $removeOptions, true);
    }

    /**
     * Append the content to the end of the file
     *
     * @param string | array $path
     * @param mixed $data
     *
     * @return bool
     */
    public function appendContents($path, $data)
    {
        return $this->putContents($path, $data, FILE_APPEND | LOCK_EX);
    }

    /**
     * Concat paths
     * @param  string | array  $paths Ex. array('pathPart1', 'pathPart2', 'pathPart3')
     * @return string
     */
    protected function concatPaths($paths)
    {
        if (is_string($paths)) {
            return $paths;
        }

        $fullPath = '';
        foreach( $paths as $path) {
            $fullPath = $this->concatPath($fullPath, $path);
        }

        return $fullPath;
    }

    /**
     * Create a new dir
     *
     * @param  string | array $path
     * @param  int $permission - ex. 0755
     * @param  bool $recursive
     *
     * @return bool
     */
//     public function mkdir($path, $permission = null, $recursive = false)
//     {
//         $fullPath = $this->concatPaths($path);

//         if (is_file($fullPath) && is_dir($path)) {
//             return true;
//         }

//         $defaultPermissions = $this->getPermissionUtils()->getDefaultPermissions();

//         if (!isset($permission)) {
//             $permission = (string) $defaultPermissions['dir'];
//             $permission = base_convert($permission, 8, 10);
//         }

//         try {
//             $result = mkdir($fullPath, $permission, true);

//             if (!empty($defaultPermissions['user'])) {
//                 $this->getPermissionUtils()->chown($fullPath);
//             }

//             if (!empty($defaultPermissions['group'])) {
//                 $this->getPermissionUtils()->chgrp($fullPath);
//             }
//         } catch (\Exception $e) {
//             //$GLOBALS['log']->critical('Permission denied: unable to create the folder on the server - '.$fullPath);
            
//         }

//         return isset($result) ? $result : false;
//     }

    /**
     * Copy files from one direcoty to another
     * Ex. $sourcePath = 'data/uploads/extensions/file.json', $destPath = 'data/uploads/backup', result will be data/uploads/backup/data/uploads/backup/file.json.
     *
     * @param  string  $sourcePath
     * @param  string  $destPath
     * @param  boolean $recursively
     * @param  array $fileList - list of files that should be copied
     * @param  boolean $copyOnlyFiles - copy only files, instead of full path with directories, Ex. $sourcePath = 'data/uploads/extensions/file.json', $destPath = 'data/uploads/backup', result will be 'data/uploads/backup/file.json'
     * @return boolen
     */
//     public function copy($sourcePath, $destPath, $recursively = false, array $fileList = null, $copyOnlyFiles = false)
//     {
//         $sourcePath = $this->concatPaths($sourcePath);
//         $destPath = $this->concatPaths($destPath);

//         if (isset($fileList)) {
//             if (!empty($sourcePath)) {
//                 foreach ($fileList as &$fileName) {
//                     $fileName = $this->concatPaths(array($sourcePath, $fileName));
//                 }
//             }
//         } else {
//             $fileList = is_file($sourcePath) ? (array) $sourcePath : $this->getFileList($sourcePath, $recursively, '', true, true);
//         }

//         /** Check permission before copying */
//         $permissionDeniedList = array();
//         foreach ($fileList as $file) {

//             if ($copyOnlyFiles) {
//                 $file = pathinfo($file, PATHINFO_BASENAME);
//             }

//             $destFile = $this->concatPaths(array($destPath, $file));

//             $isFileExists = is_file($destFile);

//             if ($this->checkCreateFile($destFile) === false) {
//                 $permissionDeniedList[] = $destFile;
//             } else if (!$isFileExists) {
//                 $this->removeFile($destFile);
//             }
//         }
//         /** END */

//         if (!empty($permissionDeniedList)) {
//             $betterPermissionList = $this->getPermissionUtils()->arrangePermissionList($permissionDeniedList);
//             throw new Error("Permission denied for <br>". implode(", <br>", $betterPermissionList));
//         }

//         $res = true;
//         foreach ($fileList as $file) {

//             if ($copyOnlyFiles) {
//                 $file = pathinfo($file, PATHINFO_BASENAME);
//             }

//             $sourceFile = is_file($sourcePath) ? $sourcePath : $this->concatPaths(array($sourcePath, $file));
//             $destFile = $this->concatPaths(array($destPath, $file));

//             if (is_file($sourceFile) && is_file($sourceFile)) {
//                 $res &= copy($sourceFile, $destFile);
//             }
//         }

//         return $res;
//     }

    /**
     * Create a new file if not exists with all folders in the path.
     *
     * @param string $filePath
     * @return string
     */
//     public function checkCreateFile($filePath)
//     {
//         $defaultPermissions = $this->getPermissionUtils()->getDefaultPermissions();

//         if (is_file($filePath)) {

//             if (!in_array($this->getPermissionUtils()->getCurrentPermission($filePath), array($defaultPermissions['file'], $defaultPermissions['dir']))) {
//                 return $this->getPermissionUtils()->setDefaultPermissions($filePath, true);
//             }
//             return true;
//         }

//         $pathParts = pathinfo($filePath);
//         if (!is_file($pathParts['dirname'])) {
//             $dirPermission = $defaultPermissions['dir'];
//             $dirPermission = is_string($dirPermission) ? base_convert($dirPermission,8,10) : $dirPermission;

//             if (!$this->mkdir($pathParts['dirname'], $dirPermission, true)) {
//                 throw new Error('Permission denied: unable to create a folder on the server - ' . $pathParts['dirname']);
//             }
//         }

//         if (touch($filePath)) {
//             return $this->getPermissionUtils()->setDefaultPermissions($filePath, true);
//         }

//         return false;
//     }

    /**
     * Remove file/files by given path
     *
     * @param array $filePaths - File paths list
     * @return bool
     */
    public function unlink($filePaths)
    {
        return $this->removeFile($filePaths);
    }

    public function rmdir($dirPaths)
    {
        if (!is_array($dirPaths)) {
            $dirPaths = (array) $dirPaths;
        }

        $result = true;
        foreach ($dirPaths as $dirPath) {
            if (is_dir($dirPath) && is_writable($dirPath)) {
                $result &= rmdir($dirPath);
            }
        }

        return (bool) $result;
    }

    /**
     * Remove file/files by given path
     *
     * @param array $filePaths - File paths list
     * @param string $dirPath - directory path
     * @return bool
     */
    public function removeFile($filePaths, $dirPath = null)
    {
        if (!is_array($filePaths)) {
            $filePaths = (array) $filePaths;
        }

        $result = true;
        foreach ($filePaths as $filePath) {
            if (isset($dirPath)) {
                $filePath = $this->concatPath($dirPath, $filePath);
            }

            if (is_file($filePath) && is_file($filePath)) {
                $result &= unlink($filePath);
            }
        }

        return $result;
    }

    /**
     * Remove all files inside given path
     *
     * @param string $dirPath - directory path
     * @param bool $removeWithDir - if remove with directory
     *
     * @return bool
     */
    public function removeInDir($dirPath, $removeWithDir = false)
    {
        $fileList = $this->getFileList($dirPath, false);

        $result = true;
        if (is_array($fileList)) {
            foreach ($fileList as $file) {
                $fullPath = $this->concatPath($dirPath, $file);
                if (is_dir($fullPath)) {
                    $result &= $this->removeInDir($fullPath, true);
                } else if (is_file($fullPath)) {
                    $result &= unlink($fullPath);
                }
            }
        }

        if ($removeWithDir) {
            $result &= $this->rmdir($dirPath);
        }

        return (bool) $result;
    }

    /**
     * Remove items (files or directories)
     *
     * @param  string | array $items
     * @param  string $dirPath
     * @return boolean
     */
//     public function remove($items, $dirPath = null, $removeEmptyDirs = false)
//     {
//         if (!is_array($items)) {
//             $items = (array) $items;
//         }

//         $permissionDeniedList = array();
//         foreach ($items as $item) {
//             if (isset($dirPath)) {
//                 $item = $this->concatPath($dirPath, $item);
//             }

//             if (!is_writable($item)) {
//                 $permissionDeniedList[] = $item;
//             } else if (!is_writable(dirname($item))) {
//                 $permissionDeniedList[] = dirname($item);
//             }
//         }

//         if( ! empty( $permissionDeniedList)) {
//             $betterPermissionList = $this->getPermissionUtils()->arrangePermissionList($permissionDeniedList);
//             throw new Error( 'Permission denied for <br>'. implode( ', <br>', $betterPermissionList));
//         }

//         $result = true;
//         foreach( $items as $item) {
// 	            if( isset( $dirPath)) {
// 	                $item = $this->concatPath( $dirPath, $item);
// 	            }
	
// 	            if (is_dir($item)) {
// 	                $result &= $this->removeInDir( $item, true);
// 	            } else {
// 	                $result &= $this->removeFile( $item);
// 	            }
	
// 	            if ($removeEmptyDirs) {
// 	                $result &= $this->removeEmptyDirs( $item);
// 	            }
// 	     }
	
// 	     return (bool) $result;
//     }

    /**
     * Remove empty parent directories if they are empty
     * @param  string $path
     * @return bool
     */
    protected function removeEmptyDirs($path)
    {
        $parentDirName = $this->getParentDirName($path);

        $res = true;
        if ($this->isDirEmpty($parentDirName)) {
            $res &= $this->rmdir($parentDirName);
            $res &= $this->removeEmptyDirs($parentDirName);
        }

        return (bool) $res;
    }

    /**
     * Check if $dirname is directory.
     *
     * @param  string  $dirname
     * @return boolean
     */
    public function isDir($dirname)
    {
        return is_dir($dirname);
    }

    /**
     * Check if $filename is file. If $filename doesn'ot exist, check by pathinfo
     *
     * @param  string  $filename
     * @return boolean
     */
    public function isFile($filename)
    {
        if (is_file($filename)) {
            return is_file($filename);
        }

        $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
        if (!empty($fileExtension)) {
            return true;
        }

        return false;
    }

    /**
     * Check if directory is empty
     * @param  string  $path
     * @return boolean
     */
    public function isDirEmpty($path)
    {
        if (is_dir($path)) {
            $fileList = $this->getFileList($path, true);

            if (is_array($fileList) && empty($fileList)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a filename without the file extension
     *
     * @param string $filename
     * @param string $ext - extension, ex. '.json'
     *
     * @return array
     */
    public function getFileName($fileName, $ext='')
    {
        if (empty($ext)) {
            $fileName= substr($fileName, 0, strrpos($fileName, '.', -1));
        }
        else {
            if (substr($ext, 0, 1)!='.') {
                $ext= '.'.$ext;
            }

            if (substr($fileName, -(strlen($ext)))==$ext) {
                $fileName= substr($fileName, 0, -(strlen($ext)));
            }
        }

        $exFileName = explode('/', $this->toFormat($fileName, '/'));

        return end($exFileName);
    }
    
    /**
     * Convert to format with defined delimeter
     * ex. Fox/Utils to Fox\Utils
     *
     * @param string $name
     * @param string $delim - delimiter
     *
     * @return string
     */
    public function toFormat( $name, $delim = '/') {
    	return preg_replace( "/[\/\\\]/", $delim, $name);
    }

    /**
     * Get a directory name from the path
     *
     * @param string $path
     * @param bool $isFullPath
     *
     * @return array
     */
    public function getDirName($path, $isFullPath = true, $useIsDir = true)
    {
        $dirName = preg_replace('/\/$/i', '', $path);
        $dirName = ($useIsDir && is_dir($dirName)) ? $dirName : pathinfo($dirName, PATHINFO_DIRNAME);

        if (!$isFullPath) {
            $pieces = explode('/', $dirName);
            $dirName = $pieces[count($pieces)-1];
        }

        return $dirName;
    }

    /**
     * Get parent dir name/path
     *
     * @param  string  $path
     * @param  boolean $isFullPath
     * @return string
     */
    public function getParentDirName($path, $isFullPath = true)
    {
        return $this->getDirName($path, $isFullPath, false);
    }

    /**
     * Return content of PHP file
     *
     * @param string $varName - name of variable which contains the content
     * @param array $content
     *
     * @return string | false
     */
    public function getPHPFormat($content)
    {
        if (!isset($content)) {
            return false;
        }

        return '<?php
return '.var_export($content, true).';

?>';
    }

    /**
     * Check if $paths are writable. Permission denied list are defined in getLastPermissionDeniedList()
     *
     * @param  array   $paths
     *
     * @return boolean
     */
//     public function isWritableList(array $paths)
//     {
//         $permissionDeniedList = array();

//         $result = true;
//         foreach ($paths as $path) {
//             $rowResult = $this->isWritable($path);
//             if (!$rowResult) {
//                 $permissionDeniedList[] = $path;
//             }
//             $result &= $rowResult;
//         }

//         if (!empty($permissionDeniedList)) {
//             $this->permissionDeniedList = $this->getPermissionUtils()->arrangePermissionList($permissionDeniedList);
//         }

//         return (bool) $result;
//     }

    /**
     * Get last permission denied list
     *
     * @return array
     */
    public function getLastPermissionDeniedList()
    {
        return $this->permissionDeniedList;
    }

    /**
     * Check if $path is writable
     *
     * @param  string | array  $path
     *
     * @return boolean
     */
    public function isWritable($path)
    {
        $existFile = $this->getExistsPath($path);

        return is_writable($existFile);
    }

    /**
     * Get exists path. Ex. if check /var/www/answeredtime/custom/someFile.php and this file doesn't extist, result will be /var/www/answeredtime/custom
     *
     * @param  string | array $path
     *
     * @return string
     */
    protected function getExistsPath($path)
    {
        $fullPath = $this->concatPaths($path);

        if (!is_file($fullPath)) {
            $fullPath = $this->getExistsPath(pathinfo($fullPath, PATHINFO_DIRNAME));
        }

        return $fullPath;
    }
    
}
