
1.5 *** New features ***

- Implemented ArrayAccess to use this object as array
- added getData and setData to get and set the whole shared object (must be done after a lock()!)
- added unit tests on array crud, getData and setData

1.4 *** New features ***

- Added timeout and interval to the lock (actually it was infinite until unlock, quite unsafe if a process crashes)
- Implemented __isset and __unset to complete \stdClass logic
- Added unit tests on lock / unlock / __isset and __unset
- Added aliases (has/get/set/remove) of magic methods for better OOP design and to access shared _file and _lock properties

1.3 *** Added Mutex-style locks ***

If flock() function avoid concurrent access to the synchronization file, there were still a problem with concurrent access
to shared variables. See Sync.demo.3.php for more details about the subject.

1.2 *** Replaced usage of json_* functions by *serialize functions

It takes less disk space to save json data, but this class intends to accept any serializable data. So serialize is a better
candidate.

1.1 *** Changed name (Synchro <-> Sync)

This is a shared object, synchronized on a file.

1.0 *** Original version ***

See http://stackoverflow.com/questions/16415206/how-can-php-do-centralized-curl-multi-requests/16573405#16573405
