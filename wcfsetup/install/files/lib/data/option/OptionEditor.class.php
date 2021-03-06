<?php
namespace wcf\data\option;
use wcf\data\DatabaseObjectEditor;
use wcf\data\IEditableCachedObject;
use wcf\system\cache\builder\OptionCacheBuilder;
use wcf\system\cache\CacheHandler;
use wcf\system\io\File;
use wcf\system\WCF;
use wcf\util\FileUtil;

/**
 * Provides functions to edit options.
 * 
 * @author	Alexander Ebert
 * @copyright	2001-2013 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	data.option
 * @category	Community Framework
 */
class OptionEditor extends DatabaseObjectEditor implements IEditableCachedObject {
	/**
	 * options cache file name
	 * @var	string
	 */
	const FILENAME = 'options.inc.php';
	
	/**
	 * @see	wcf\data\DatabaseObjectDecorator::$baseClass
	 */
	protected static $baseClass = 'wcf\data\option\Option';
	
	/**
	 * Imports the given options.
	 * 
	 * @param	array		$options	name to value
	 */
	public static function import(array $options) {
		// get option ids
		$sql = "SELECT		optionName, optionID
			FROM		wcf".WCF_N."_option";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute();
		$optionIDs = array();
		while ($row = $statement->fetchArray()) {
			$optionIDs[$row['optionName']] = $row['optionID'];
		}
		
		$newOptions = array();
		foreach ($options as $name => $value) {
			if (isset($optionIDs[$name])) {
				$newOptions[$optionIDs[$name]] = $value;
			}
		}
		
		self::updateAll($newOptions);
	}
	
	/**
	 * Updates the values of the given options.
	 * 
	 * @param	array		$options	id to value
	 */
	public static function updateAll(array $options) {
		$sql = "SELECT	optionID, optionValue
			FROM	wcf".WCF_N."_option
			WHERE	optionName = ?";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute(array('cache_source_type'));
		$row = $statement->fetchArray();
		
		$sql = "UPDATE	wcf".WCF_N."_option
			SET	optionValue = ?
			WHERE	optionID = ?";
		$statement = WCF::getDB()->prepareStatement($sql);
		
		$flushCache = false;
		foreach ($options as $id => $value) {
			if ($id == $row['optionID'] && ($value != $row['optionValue'] || $value != CACHE_SOURCE_TYPE)) {
				$flushCache = true;
			}
			
			$statement->execute(array(
				$value,
				$id
			));
		}
		
		// force a cache reset if options were changed
		self::resetCache();
		
		// flush entire cache, as the CacheSource was changed
		if ($flushCache) {
			// flush caches (in case register_shutdown_function gets not properly called)
			CacheHandler::getInstance()->flushAll();
			
			// flush cache before finishing request to flush caches created after this was executed
			register_shutdown_function(function() {
				CacheHandler::getInstance()->flushAll();
			});
		}
	}
	
	/**
	 * @see	wcf\data\IEditableCachedObject::resetCache()
	 */
	public static function resetCache() {
		// reset cache
		OptionCacheBuilder::getInstance()->reset();
		
		// reset options.inc.php files
		self::rebuild();
	}
	
	/**
	 * Rebuilds the option file.
	 */
	public static function rebuild() {
		$buffer = '';
		
		// file header
		$buffer .= "<?php\n/**\n* generated at ".gmdate('r')."\n*/\n";
		
		// get all options
		$options = Option::getOptions();
		foreach ($options as $optionName => $option) {
			$buffer .= "if (!defined('".$optionName."')) define('".$optionName."', ".(($option->optionType == 'boolean' || $option->optionType == 'integer') ? intval($option->optionValue) : "'".addcslashes($option->optionValue, "'\\")."'").");\n";
		}
		unset($options);
		
		// file footer
		$buffer .= "\n";
		
		// open file
		$file = new File(WCF_DIR.'options.inc.php');
		
		// write buffer
		$file->write($buffer);
		
		// close file
		$file->close();
		FileUtil::makeWritable(WCF_DIR.'options.inc.php');
	}
}
