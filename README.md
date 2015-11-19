# WoodyAttachments file upload plugin for CakePHP 3
CakePHP 3 file upload plugin.

## Requirements
CakePHP 3
PHP > 5.4.16

imagine/imagine is used for image manipulation

## Installation

add to composer "GrandFelix/WoodyAttachments": "*"

run composer update

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

```
composer require your-name-here/WoodyAttachments
```
