# WoodyAttachments CakePHP 3 plugin
CakePHP 3 file upload plugin.

## Requirements
CakePHP 3
PHP > 5.4.16

imagine/imagine is used for image manipulation

## Installation

```
composer require GrandFelix/WoodyAttachments
```

run composer update in shell

run migrations
```
bin/cake migrations migrate --plugin WoodyAttachments
```

## How to use it
Add this to your Table file:

```php
$this->addBehavior('WoodyAttachments.Upload', [
            'fields' => [
                'images' => [
                    'allowedFileTypes' => ['image/jpeg', 'image/png'],
                    'fileSize' => '2MB',
                    'numberOfFiles' => 20,
                    'operations' => [ // remove all this if you don't want to make any file operations
                        'thumb' => [ // this wil be folder name uploads folder
                            'thumbnail' => [
                                'w' => 270,
                                'h' => 198
                            ],
                            'effects' => ['negative'] // add Imagine effects
                        ],
                        'mid' => [ // this wil be folder name uploads folder
                            'widen' => [
                                'size' => 600
                            ],
                            'effects' => ['grayscale'],
                            'rotate' => ['degrees' => 90]
                        ],
                    ]
                ],
                'pdf' => [
                    'allowedFileTypes' => ['application/pdf'],
                    'fileSize' => '2MB',
                ]
            ]
        ]);
```

Currently there are only file operations for images...


For multiple upload add this to your view file:

```php
echo $this->Form->input('images[]',
    [
        'multiple' => true,
        'label' => 'Slike',
        'type' => 'file'
    ]);
```
            
or this for single upload:

```php
echo $this->Form->input('pdf',
    [
        'label' => 'PDF',
        'type' => 'file'
    ]);
```         

And don't forget to add ['type' => 'file'] in $this->Form->create('ModelName', ['type' => 'file'])

## Other
 - Files are saved in webroot/{your_file_upload_folder_from_config}/{Year}/{Month}/{Model}/*
 - when you delete item, files will also be deleted...

## TODO
 - A lot of code improvements because it was all created very fast
 - Move file operations to other place..
 - Create helper to render files automatically
 - Administration controller
 - Configurable file saving paths
 - Recognize file type and use file operations for that file type...
 - ...