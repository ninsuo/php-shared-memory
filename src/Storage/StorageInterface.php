<?php

namespace Fuz\Component\SharedMemory\Storage;

use Fuz\Component\SharedMemory\Entity\StoredEntity;

/**
 * A "Storage" is something where the StorageEntity can be stored
 * for sharing. A file, a database, IPCs or whatever resources that
 * can be accessed by N applications without concurrent access.
 *
 * @license http://opensource.org/licenses/MIT
 * @author Alain Tiemblo <alain@fuz.org>
 * @version 2.0
 */
interface StorageInterface
{

    /**
     * Opens the storage for reading: several applications can read the
     * resource at the same time; but it becomes readonly until all
     * applications close it.
     */
    public function openReader();

    /**
     * Opens the storage for writting: only one application can open
     * the resource at once, if other applications try to open a reader
     * or a writter, they should wait for the exclusive lock to be released.
     */
    public function openWriter();

    /**
     * Recovers the Storage object from the resource.
     */
    public function getObject();

    /**
     * Save the Storage object to the resource.
     *
     * @param StorageEntity $object
     */
    public function setObject(StoredEntity $object);

    /**
     * Closes the storage, releasing the resource.
     */
    public function close();

    /**
     * Storage name
     */
    public function getName();

}
