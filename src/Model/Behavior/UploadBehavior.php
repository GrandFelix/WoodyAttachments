<?php
namespace WoodyAttachments\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\Event\Event;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use ArrayObject;
use Cake\Filesystem\Folder;
use Cake\Filesystem\File;
use Cake\Core\Configure;
use Cake\Utility\Text;
use Cake\Datasource\EntityInterface;
use Imagine\Image\ImageInterface;
use WoodyAttachments\Model\Entity\Attachment;

class UploadBehavior extends Behavior
{
    private $Imagine = null;

    protected $_defaultConfig = [
        'defaultFieldOptions' => [
            'allowedFileTypes' => null,
            'fileSize' => '1MB',
            'required' => false, // create, update, true, false
            'operations' => []
        ]
    ];

    public function __construct(Table $table, array $config = [])
    {
        parent::__construct($table, $config);

    }

    /**
     * Initialize hook
     *
     * @param array $config The config for this behavior.
     * @return void
     */
    public function initialize(array $config)
    {
        $this->_table->hasMany('Attachments', [
            'className' => 'WoodyAttachments\Model\Table\AttachmentsTable',
            'foreignKey' => 'entity_id',
            'dependent' => true,
            'conditions' => ['Attachments.entity' => $this->_table->alias()]
        ]);

    }

    public function beforeFind(Event $event, Query $query, ArrayObject $options, $primary)
    {
        $query->contain(['Attachments']);
    }

    public function buildValidator(Event $event, Validator $validator, $name)
    {
        if($this->_config['fields']) {
            $validator->provider('fileupload', 'WoodyAttachments\Model\Validation\FileUploadValidator');

            foreach ($this->_config['fields'] as $field => $fieldOptions) {
                $options = array_merge($this->_config['defaultFieldOptions'], $fieldOptions);
                if (isset($options['numberOfFiles'])) {
                    //$field .= '.';
                }

                if ($options['required']) {
                    $validator->notEmpty($field, __d('WoodyAttachments', 'This filed is required'), $options['required']);
                } else {
                    $validator->allowEmpty($field, true);
                }

                $validator
                    ->add($field, [
                        'mime' => [
                            'rule' => ['mimeType', $options['allowedFileTypes']],
                            'message' => __d('WoodyAttachments', 'One of the files is not allowed file type.'),
                            'provider' => 'fileupload',
                            'on' => function ($context) use ($options, $field) {
                                return (
                                    (!empty($options['allowedFileTypes']) && !empty($context['data'][$field][0]['tmp_name'])) ||
                                    (!empty($options['allowedFileTypes']) && !empty($context['data'][$field]['tmp_name']))
                                );
                            }
                        ],
                        'size' => [
                            'rule' => ['fileSize', '<', $options['fileSize']],
                            'message' => __d('WoodyAttachments', 'One of the files is too large.'),
                            'provider' => 'fileupload',
                            'on' => function ($context) use ($options, $field) {
                                return (
                                    (!is_null($options['fileSize']) && !empty($context['data'][$field][0]['tmp_name'])) ||
                                    (!is_null($options['fileSize']) && !empty($context['data'][$field]['tmp_name']))
                                );
                            }
                        ],
                        'error' => [
                            'rule' => ['uploadErrorCheck'],
                            'message' => __d('WoodyAttachments', 'Upload error.'),
                            'provider' => 'fileupload',
                            'on' => function ($context) use ($options, $field) {
                                return (
                                    (!empty($context['data'][$field][0]['tmp_name'])) ||
                                    (!empty($context['data'][$field]['tmp_name']))
                                );
                            }
                        ]
                    ]);
                unset($options);
            }

            return $validator;
        }

        throw new \BadFunctionCallException(__d('WoodyAttachments', 'You must specify fields field in WoodyAttachmentsUploadBehavior!'));
    }



    public function beforeDelete(Event $event, Entity $entity, ArrayObject $options)
    {
        $attachments = TableRegistry::get('WoodyAttachments.Attachments');
        $return = $attachments->deleteAttachments($entity->get('attachments'), true);

        return $return;
    }

    public function afterSave(Event $event, Entity $entity, ArrayObject $options)
    {
        $ulPathSuffix = DS . date('Y') . DS . date('m') . DS . $this->_table->alias() . DS;
        $folder = new Folder(Configure::read('Upload.path') . $ulPathSuffix, true, 755);
        $table = TableRegistry::get('WoodyAttachments.Attachments');

        foreach($this->_config['fields'] as $field => $options) {
            if (isset($entity->get($field)[0]['tmp_name'])) {
                foreach ($entity->get($field) as $file) {
                    $newFileName = substr(Text::uuid(), 0, 13) . '-' . $file['name'];
                    $attachment = array();
                    if (move_uploaded_file($file['tmp_name'], $folder->path . $newFileName)) {
                        $attachment['file_name'] = $newFileName;
                        $attachment['file_path'] = $ulPathSuffix;
                        $attachment['file_size'] = $file['size'];
                        $attachment['mime_type'] = $file['type'];
                        $attachment['real_name'] = $file['name'];
                        $attachment['entity'] = $this->_table->alias();
                        $attachment['entity_id'] = $entity->get('id');
                        $attachmentEntity = $table->newEntity($attachment);

                        if($table->save($attachmentEntity)) {
                            $this->runFileOperations($attachmentEntity, $options);
                        }

                        unset($attachment);
                    }
                }
            } else {
                $file = $entity->get($field);
                $newFileName = substr(Text::uuid(), 0, 13) . '-' . $file['name'];

                if (move_uploaded_file($file['tmp_name'], $folder->path . $newFileName)) {
                    $attachment['file_name'] = $newFileName;
                    $attachment['file_path'] = $ulPathSuffix;
                    $attachment['file_size'] = $file['size'];
                    $attachment['mime_type'] = $file['type'];
                    $attachment['real_name'] = $file['name'];
                    $attachment['entity'] = $this->_table->alias();
                    $attachment['entity_id'] = $entity->get('id');
                    $attachmentEntity = $table->newEntity($attachment);

                    if($table->save($attachmentEntity)) {
                        $this->runFileOperations($attachmentEntity, $options);
                    }
                }
            }
        }
        return true;
    }

    private function runFileOperations($attachment, $options)
    {
        $this->Imagine = new \Imagine\Gd\Imagine();
        if(isset($options['operations'])) {

            $file = Configure::read('Upload.path').$attachment->get('file_path').$attachment->get('file_name');

            foreach ($options['operations'] as $dirKey => $operations) {

                if(!is_array($operations)) {
                    throw new \InvalidArgumentException(__d('WoodyAttachments', 'File operations must be an array'));
                }

                $folder = new Folder(Configure::read('Upload.path') . $attachment->get('file_path') . $dirKey . DS, true);
                $to = Configure::read('Upload.path') . $attachment->get('file_path') . $dirKey . DS . $attachment->get('file_name');

                $image = $this->Imagine->open($file);

                foreach($operations as $operation => $operationOptions) {

                    if (method_exists($this, $operation)) {
                        $this->{$operation}($image, $operationOptions);

                        $image->save($to);
                    }

                }
                unset($image);
            }
        }
    }


    public function resize($image, $options = [])
    {
        if(empty($options['w']) && empty($options['h'])) {
            throw new \InvalidArgumentException(__d('WoodyAttachments', 'You must set width and height!'));
        }

        $image->resize(new \Imagine\Image\Box($options['w'], $options['h']));
    }

    public function thumbnail(&$image, $options = [])
    {
        if(empty($options['w']) && empty($options['h'])) {
            throw new \InvalidArgumentException(__d('WoodyAttachments', 'You must set width and height!'));
        }

        $mode = \Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;
        if(isset($options['mode']) && $options['mode'] == 'inset') {
            $mode = \Imagine\Image\ImageInterface::THUMBNAIL_INSET;
        }

        $image = $image->thumbnail(new \Imagine\Image\Box($options['w'], $options['h']), $mode);

    }

    public function rotate(ImageInterface $image, $options = [])
    {
        $defaults = [
            'degrees' => 0
        ];

        $options = array_merge($defaults, $options);

        $image->rotate($options['degrees']);
    }

    public function crop($image, $options = []) {
        if (empty($options['h']) || empty($options['w'])) {
            throw new \InvalidArgumentException(__d('woody_upload', 'You must set width and height options!'));
        }

        $defaults = [
            'cropX' => 0,
            'cropY' => 0
        ];
        $options = array_merge($defaults, $options);

        $image->crop(new \Imagine\Image\Point($options['cropX'], $options['cropY']), new \Imagine\Image\Box($options['w'], $options['h']));
    }

    public function effects(&$image, $options = [])
    {
        if(is_array($options)) {
            foreach($options as $key => $effect) {
                if(method_exists($image->effects(), $effect)) {
                    $image->effects()->{$effect}();
                }
            }
        }

    }

    public function widen($image, $options = array()) {
        if (empty($options['size'])) {
            throw new \InvalidArgumentException(__d('Imagine', 'You must pass a size value!'));
        }
        $this->widenAndHeighten($image, array('width' => $options['size']));
    }

    public function heighten($image, $options = array()) {
        if (empty($options['size'])) {
            throw new \InvalidArgumentException(__d('Imagine', 'You must pass a size value!'));
        }
        $this->widenAndHeighten($image, array('height' => $options['size']));
    }

    public function widenAndHeighten($image, $options = array()) {
        if (empty($options['height']) && empty($options['width']) && empty($options['size'])) {
            throw new \InvalidArgumentException(__d('Imagine', 'You have to pass a height, width or size!'));
        }
        if (!empty($options['height']) && !empty($options['width'])) {
            throw new \InvalidArgumentException(__d('Imagine', 'You can only scale by width or height!'));
        }
        if (isset($options['width'])) {
            $size = $options['width'];
            $method = 'widen';
        } elseif (isset($options['height'])) {
            $size = $options['height'];
            $method = 'heighten';
        } else {
            $size = $options['size'];
            $method = 'scale';
        }
        $imageSize = $this->getImageSize($image);

        $width = $imageSize[0];
        $height = $imageSize[1];

        if (isset($options['noUpScale'])) {
            if ($method == 'widen') {
                if ($size > $width) {
                    throw new \InvalidArgumentException(__d('Imagine', 'You can not scale up!'));
                }
            } elseif ('heighten') {
                if ($size > $height) {
                    throw new \InvalidArgumentException(__d('Imagine', 'You can not scale up!'));
                }
            }
        }

        if (isset($options['noDownScale'])) {
            if ($method == 'widen') {
                if ($size < $width) {
                    throw new \InvalidArgumentException(__d('Imagine', 'You can not scale down!'));
                }
            } elseif ('heighten') {
                if ($size < $height) {
                    throw new \InvalidArgumentException(__d('Imagine', 'You can not scale down!'));
                }
            }
        }

        $Box = new \Imagine\Image\Box($width, $height);

        $Box = $Box->{$method}($size);

        $image->resize($Box);
    }

    public function getImageSize($image)
    {
        if (is_string($image)) {
            $class = '\Imagine\Gd\Imagine';
            $imagine = new $class();
            $image = $imagine->open($image);
        }

        $BoxInterface = $image->getSize($image);

        return array(
            $BoxInterface->getWidth(),
            $BoxInterface->getHeight(),
            'x' => $BoxInterface->getWidth(),
            'y' => $BoxInterface->getHeight()
        );
    }

}