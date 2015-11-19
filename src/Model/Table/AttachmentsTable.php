<?php
namespace WoodyAttachments\Model\Table;

use WoodyAttachments\Model\Entity\Attachment;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Filesystem\Folder;
use Cake\Filesystem\File;
use Cake\Core\Configure;
use Cake\Event\Event;

/**
 * Attachments Model
 */
class AttachmentsTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->table('attachments');
        $this->displayField('file_name');
        $this->primaryKey('id');

        $this->addBehavior('Timestamp');

    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->add('id', 'valid', ['rule' => 'numeric'])
            ->allowEmpty('id', 'create')
            ->requirePresence('file_name', 'create')
            ->notEmpty('file_name');

        return $validator;
    }

    public function beforeDelete(Event $event, Attachment $entity, \ArrayObject $options)
    {
        return $this->deleteAttachments($entity, true);
    }

    public function deleteAttachments($attachments, $recursive = false)
    {
        $return = true;
        if(is_array($attachments)) {
            foreach($attachments as $attachment) {
                $return = $this->deleteFile($attachment, $recursive);
            }
        } elseif(is_object($attachments) && $attachments instanceof Attachment) {
            $return = $this->deleteFile($attachments, $recursive);
        }

        return $return;
    }

    private function deleteFile(Attachment $attachment, $recursive = false)
    {
        $return = true;
        $dir = new Folder(Configure::read('Upload.path') . $attachment->get('file_path'));
        if($recursive) {
            $files = $dir->findRecursive($attachment->get('file_name') . '.*', true);
        } else {
            $files = $dir->find($attachment->get('file_name') . '.*');
        }

        foreach ($files as $file) {
            $fileTmp = new File($file);

            if ($fileTmp->exists()) {
                if(!$fileTmp->delete()) {
                    $return = false;
                }
            } else {
                $return = false;
            }
        }

        return $return;
    }

}
