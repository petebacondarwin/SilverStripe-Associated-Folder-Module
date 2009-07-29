<?php
/**
 * Decorate a data object so that it has an associated folder on the file system that
 * lives and dies with this object.
 * You can access this associated folder from the object via myObj->AssociatedFolder()
 * Extend a DataObject class by calling
 * DataObject::add_extension('MyDataObject', 'AssociatedFolderDecorator');
 *
 */
class AssociatedFolderDecorator extends DataObjectDecorator {
	function extraDBFields() {
		return array(
			'has_one' => array(
				'AssociatedFolder' => 'Folder'
				)
				);
	}

	/**
	 * The folder name to use if the decorated object's parent has no associated folder of its own.
	 *
	 * @var string
	 */
	protected static $defaultRootFolderName = "associated-folders";
	/**
	 * Set the folder name to use if the decorated object's parent has no associated folder of its own.
	 *
	 * @param string $folderName
	 */
	static function setDefaultRootFolderName($folderName) {
		AssociatedFolderDecorator::$defaultRootFolderName = $folderName;
	}
	/**
	 * Get the folder name to use if the decorated object's parent has no associated folder of its own.
	 *
	 * @return string
	 */
	static function getDefaultRootFolderName() {
		return AssociatedFolderDecorator::$defaultRootFolderName;
	}
	
	/**
	 * Do stuff before the decorated object is written.
	 * In this case create the associated folder if it does not already exist.
	 *
	 */
	function onBeforeWrite() {
		parent::onBeforeWrite();
		if( ! $this->owner->AssociatedFolderID ) {
			$this->createAssociatedFolder();
		}
	}
	
	/**
	 * Create the associated folder
	 *
	 * @return Folder
	 */
	function createAssociatedFolder() {
		$parent = $this->owner->Parent;
		if ( $parent && $parent->hasExtension($this->class) ) {
			$parentFolder = $parent->AssociatedFolder();
			$parentFolderName = str_replace('assets/','',$parentFolder->FileName);
		} else {
			$parentFolderName = $this->stat('defaultRootFolderName');
		}
		$associatedFolder = Folder::findOrMake($parentFolderName . '/' . $this->owner->URLSegment);
		$this->owner->AssociatedFolderID = $associatedFolder->ID;
		return $associatedFolder;
	}

	/**
	 * Do stuff after the decorated object is written.
	 * In this case update the associated folder to match the title of the decorated object.
	 *
	 */
	function onAfterWrite() {
		if( $this->owner->ID ) {
			$this->updateAssociatedFolder();
		}
		parent::onAfterWrite();
	}

	/**
	 * Update the folder that holds the photos to have the same file name as this page's title.
	 *
	 */
	function updateAssociatedFolder() {
		$changedFields = $this->owner->getChangedFields();
		$parentChanged = array_key_exists('ParentID', $changedFields );
		$folder = $this->owner->AssociatedFolder();
		if ( $folder ) {
			$folder->Title = $this->owner->Title;
			$folder->Name = $this->owner->URLSegment;
			if ( $parentChanged ) {
				// The parent has changed move the folder if possible
				$parent = DataObject::get_by_id('Page', $changedFields['ParentID']['after']);
				if ( $parent &&  $parent->hasExtension($this->class) ) {
					// Move the folder to the new parent
					$folder->ParentId = $parent->AssociatedFolder()->ID;
				} else {
					// Move the folder below the default root as its owner's parent is not folder associated
					$root = Folder::findOrMake($this->stat('defaultRootFolderName'));
					$folder->ParentId = $root->ID;
				}
			}
			$folder->write();
		} else {
			trigger_error("Associated folder does not exist", E_USER_ERROR);
		}
	}

	/**
	 * Do stuff before the decorated object is deleted.
	 * In this case unlink (delete) the associated folder.
	 *
	 */
	function onBeforeDelete() {
		parent::onBeforeDelete();
		$this->deleteFolderAssociatedChildPages();
		$folder = $this->owner->AssociatedFolder();
		if ( $folder ) {
			$folder->delete();
		} else {
			trigger_error("Associated folder does not exist", E_USER_ERROR);
		}
	}

	/**
	 * Silverstripe does not delete child objects by default.
	 * This seems a bit weird behaviour and since deleting this object's associated folder will
	 * delete all sub-folders we ought to delete any child pages that are associated with
	 * folders to stop them from becoming bad.
	 *
	 */
	function deleteFolderAssociatedChildPages() {
		$childPages = DataObject::get('Page', 'ParentId = '.$this->owner->ID);
		if ( $childPages ) {
			foreach($childPages as $childPage) {
				if ( $childPage->hasExtension($this->class) ) {
					$childPage->delete();
				}
			}
		}
	}
}