<?php
/**
 * Decorate a data object so that it has an associated folder on the file system that
 * lives and dies with this object.
 * You can access this associated folder from the object via myObj->AssociatedFolder()
 * Extend a DataObject class by calling
 * DataObject::add_extension('MyDataObject', 'AssociatedFolderDecorator');
 *
 */
class AssociatedFolderDecorator extends SiteTreeDecorator {
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

        $folder = null;
        
        // Check whether the folder id is 0 or the Folder object does not exist
        if ( $this->owner->AssociatedFolderID != 0 ) {
            $folder = DataObject::get_by_id('Folder', $this->owner->AssociatedFolderID);
        }

        if( !$folder ) {
            // A valid folder does not exist
            $this->createAssociatedFolder();
        } else {
            if ( !file_exists($folder->getFullPath()) ) {
                // The folder object may exist but the folder on the file system does not.
                mkdir($folder->getFullPath(),Filesystem::$folder_create_mask);
            }
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
     * Do stuff after the decorated object is published.
     * In this case update the associated folder to match the title of the decorated object.
     * This way the filename and position in the folder tree always matches the published version.
     *
     */
    function onAfterPublish($original) {
        parent::onAfterPublish($original);
        if( $this->owner->ID ) {
            $this->updateAssociatedFolder($original);
        }
    }

    /**
     * Update the folder that holds the photos to have the same file name as this page's title.
     *
     */
    function updateAssociatedFolder($original) {        
        $folder = $this->owner->AssociatedFolder();
        if ( $this->owner->AssociatedFolderID && $folder ) {
            $folder->Title = $this->owner->Title;
            $folder->Name = $this->owner->URLSegment;
            if ( $this->owner->parentID != $original->ParentID ) {
                // The parent has changed move the folder if possible
                $parent = $this->owner->Parent();
                if ( $parent &&  $parent->hasExtension($this->class) ) {
                    // Move the folder to the new parent
                    $folder->ParentId = $parent->AssociatedFolderID;
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
     * In this case rename the associated folder.
     *
     */
    function onBeforeUnpublish() {
        parent::onBeforeUnpublish();
        $folder = $this->owner->AssociatedFolder();
        if ( $folder ) {
            $folder->Name = $this->owner->URLSegment . '__deleted';
            $folder->write();
        } else {
            trigger_error("Associated folder does not exist", E_USER_ERROR);
        }
    }
}