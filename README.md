Associated Folder Module for SilverStripe CMS
=============================================

Use this module to associated a folder in the file system with a Page in the CMS.

Decorate a data object so that it has an associated folder on the file system that lives and dies with this object.

* You can access this associated folder from the object via myObj->AssociatedFolder()
* Extend a DataObject class by calling DataObject::add_extension('MyDataObject', 'AssociatedFolderDecorator');
