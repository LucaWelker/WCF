<?php
namespace wcf\system\importer;
use wcf\data\attachment\AttachmentAction;
use wcf\data\attachment\AttachmentEditor;
use wcf\system\exception\SystemException;

/**
 * Imports attachments.
 *
 * @author	Marcel Werk
 * @copyright	2001-2013 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	system.importer
 * @category	Community Framework
 */
class AbstractAttachmentImporter implements IImporter {
	/**
	 * object type id for attachment
	 * @var integer
	 */
	protected $objectTypeID = 0;
	
	/**
	 * @see wcf\system\importer\IImporter::import()
	 */
	public function import($oldID, array $data) {
		$fileLocation = $data['fileLocation'];
		unset($data['fileLocation']);
		
		// check file location
		if (!@file_exists($fileLocation)) return 0;
		
		// get file hash
		if (empty($data['fileHash'])) $data['fileHash'] = sha1_file($fileLocation);
		
		// get user id
		if ($data['userID']) $data['userID'] = ImportHandler::getInstance()->getNewID('com.woltlab.wcf.user', $data['userID']);
		
		// save attachment
		$action = new AttachmentAction(array(), 'create', array(
			'data' => array_merge($data, array('objectTypeID' => $this->objectTypeID))		
		));
		$returnValues = $action->executeAction();
		$attachment = $returnValues['returnValues'];
		
		// check attachment directory
		// and create subdirectory if necessary
		$dir = dirname($attachment->getLocation());
		if (!@file_exists($dir)) {
			@mkdir($dir, 0777);
		}
		
		// copy file
		try {
			if (!copy($fileLocation, $attachment->getLocation())) {
				throw new SystemException();
			}
				
			// create thumbnails
			if (ATTACHMENT_ENABLE_THUMBNAILS) {
				if ($attachment->isImage) {
					try {
						$action = new AttachmentAction(array($attachment), 'generateThumbnails');
						$action->executeAction();
					}
					catch (SystemException $e) {}	
				}
			}
			
			return $attachment->attachmentID;
		}
		catch (SystemException $e) {
			// copy failed; delete attachment
			$editor = new AttachmentEditor($attachment);
			$editor->delete();
		}
		
		return 0;
	}
}
