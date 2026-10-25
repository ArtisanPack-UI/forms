<?php

/**
 * Field-type behaviour captured from 1.6.0 before the field-type handler
 * refactor (#91). FieldTypeCharacterizationTest compares the handlers against
 * it, so the refactor cannot change built-in behaviour unnoticed.
 *
 * Deliberate changes since: URL fields accept only `http` and `https` unless
 * they allow other schemes (#120), so their rule is `url:http,https`.
 */

return  [
      'types' => 
       [
        'text' => 
         [
          'icon'                   => 'o-document-text',
          'category'               => 'basic',
          'has_options'            => false,
          'supports_placeholder'   => true,
          'supports_default_value' => true,
          'validation_options'     => 
           [
            0 => 'min',
            1 => 'max',
            2 => 'pattern',
          ],
          'label'    => 'Text Input',
          'defaults' => 
           [
            'label'       => 'Text Field',
            'placeholder' => '',
          ],
        ],
        'email' => 
         [
          'icon'                   => 'o-envelope',
          'category'               => 'basic',
          'has_options'            => false,
          'supports_placeholder'   => true,
          'supports_default_value' => true,
          'validation_options'     => 
           [
            0 => 'min',
            1 => 'max',
          ],
          'label'    => 'Email',
          'defaults' => 
           [
            'label'       => 'Email Address',
            'placeholder' => 'you@example.com',
          ],
        ],
        'number' => 
         [
          'icon'                   => 'o-hashtag',
          'category'               => 'basic',
          'has_options'            => false,
          'supports_placeholder'   => true,
          'supports_default_value' => true,
          'validation_options'     => 
           [
            0 => 'min',
            1 => 'max',
            2 => 'step',
          ],
          'label'    => 'Number',
          'defaults' => 
           [
            'label'       => 'Number',
            'placeholder' => '',
          ],
        ],
        'url' => 
         [
          'icon'                   => 'o-link',
          'category'               => 'basic',
          'has_options'            => false,
          'supports_placeholder'   => true,
          'supports_default_value' => true,
          'validation_options'     => 
           [
            0 => 'min',
            1 => 'max',
          ],
          'label'    => 'URL',
          'defaults' => 
           [
            'label'       => 'Website URL',
            'placeholder' => 'https://example.com',
          ],
        ],
        'textarea' => 
         [
          'icon'                   => 'o-bars-3-bottom-left',
          'category'               => 'basic',
          'has_options'            => false,
          'supports_placeholder'   => true,
          'supports_default_value' => true,
          'validation_options'     => 
           [
            0 => 'min',
            1 => 'max',
          ],
          'label'    => 'Text Area',
          'defaults' => 
           [
            'label'        => 'Message',
            'placeholder'  => '',
            'field_config' => 
             [
              'rows' => 4,
            ],
          ],
        ],
        'hidden' => 
         [
          'icon'                   => 'o-eye-slash',
          'category'               => 'basic',
          'has_options'            => false,
          'supports_placeholder'   => false,
          'supports_default_value' => true,
          'validation_options'     => 
           [
          ],
          'label'    => 'Hidden Field',
          'defaults' => 
           [
            'label' => 'Hidden Field',
          ],
        ],
        'select' => 
         [
          'icon'                   => 'o-chevron-down',
          'category'               => 'choice',
          'has_options'            => true,
          'supports_placeholder'   => true,
          'supports_default_value' => true,
          'validation_options'     => 
           [
          ],
          'label'    => 'Dropdown Select',
          'defaults' => 
           [
            'label'        => 'Select an Option',
            'placeholder'  => 'Choose...',
            'field_config' => 
             [
              'options' => 
               [
              ],
            ],
          ],
        ],
        'radio' => 
         [
          'icon'                   => 'o-stop-circle',
          'category'               => 'choice',
          'has_options'            => true,
          'supports_placeholder'   => false,
          'supports_default_value' => true,
          'validation_options'     => 
           [
          ],
          'label'    => 'Radio Buttons',
          'defaults' => 
           [
            'label'        => 'Choose One',
            'field_config' => 
             [
              'options' => 
               [
              ],
            ],
          ],
        ],
        'checkbox' => 
         [
          'icon'                   => 'o-check-circle',
          'category'               => 'choice',
          'has_options'            => false,
          'supports_placeholder'   => false,
          'supports_default_value' => true,
          'validation_options'     => 
           [
          ],
          'label'    => 'Single Checkbox',
          'defaults' => 
           [
            'label' => 'I agree to the terms',
          ],
        ],
        'checkbox_group' => 
         [
          'icon'                   => 'o-list-bullet',
          'category'               => 'choice',
          'has_options'            => true,
          'supports_placeholder'   => false,
          'supports_default_value' => false,
          'validation_options'     => 
           [
            0 => 'min',
            1 => 'max',
          ],
          'label'    => 'Checkbox Group',
          'defaults' => 
           [
            'label'        => 'Select Options',
            'field_config' => 
             [
              'options' => 
               [
              ],
            ],
          ],
        ],
        'select_multiple' => 
         [
          'icon'                   => 'o-queue-list',
          'category'               => 'choice',
          'has_options'            => true,
          'supports_placeholder'   => false,
          'supports_default_value' => false,
          'validation_options'     => 
           [
            0 => 'min',
            1 => 'max',
          ],
          'label'    => 'Multi-Select',
          'defaults' => 
           [
            'label'        => 'Select Multiple Options',
            'field_config' => 
             [
              'options' => 
               [
              ],
            ],
          ],
        ],
        'file' => 
         [
          'icon'                   => 'o-paper-clip',
          'category'               => 'advanced',
          'has_options'            => false,
          'supports_placeholder'   => false,
          'supports_default_value' => false,
          'validation_options'     => 
           [
            0 => 'max_size',
            1 => 'allowed_types',
          ],
          'label'    => 'File Upload',
          'defaults' => 
           [
            'label'        => 'Upload File',
            'field_config' => 
             [
              'max_size'      => 5120,
              'allowed_types' => 
               [
                0 => 'pdf',
                1 => 'doc',
                2 => 'docx',
                3 => 'jpg',
                4 => 'png',
              ],
            ],
          ],
        ],
        'date' => 
         [
          'icon'                   => 'o-calendar',
          'category'               => 'advanced',
          'has_options'            => false,
          'supports_placeholder'   => false,
          'supports_default_value' => true,
          'validation_options'     => 
           [
            0 => 'min_date',
            1 => 'max_date',
          ],
          'label'    => 'Date Picker',
          'defaults' => 
           [
            'label' => 'Select Date',
          ],
        ],
        'phone' => 
         [
          'icon'                   => 'o-phone',
          'category'               => 'basic',
          'has_options'            => false,
          'supports_placeholder'   => true,
          'supports_default_value' => true,
          'validation_options'     => 
           [
            0 => 'pattern',
          ],
          'label'    => 'Phone Number',
          'defaults' => 
           [
            'label'       => 'Phone Number',
            'placeholder' => '(123) 456-7890',
          ],
        ],
        'time' => 
         [
          'icon'                   => 'o-clock',
          'category'               => 'advanced',
          'has_options'            => false,
          'supports_placeholder'   => false,
          'supports_default_value' => true,
          'validation_options'     => 
           [
            0 => 'min_time',
            1 => 'max_time',
          ],
          'label'    => 'Time Picker',
          'defaults' => 
           [
            'label'        => 'Select Time',
            'field_config' => 
             [
              'step' => 60,
            ],
          ],
        ],
        'heading' => 
         [
          'icon'                   => 'o-h1',
          'category'               => 'layout',
          'has_options'            => false,
          'supports_placeholder'   => false,
          'supports_default_value' => false,
          'is_layout'              => true,
          'validation_options'     => 
           [
          ],
          'label'    => 'Heading',
          'defaults' => 
           [
            'label'        => 'Section Heading',
            'field_config' => 
             [
              'level' => 'h3',
            ],
          ],
        ],
        'paragraph' => 
         [
          'icon'                   => 'o-document-text',
          'category'               => 'layout',
          'has_options'            => false,
          'supports_placeholder'   => false,
          'supports_default_value' => false,
          'is_layout'              => true,
          'validation_options'     => 
           [
          ],
          'label'    => 'Paragraph Text',
          'defaults' => 
           [
            'label'        => 'Information',
            'field_config' => 
             [
              'content' => 'Add descriptive text here.',
            ],
          ],
        ],
        'divider' => 
         [
          'icon'                   => 'o-minus',
          'category'               => 'layout',
          'has_options'            => false,
          'supports_placeholder'   => false,
          'supports_default_value' => false,
          'is_layout'              => true,
          'validation_options'     => 
           [
          ],
          'label'    => 'Divider',
          'defaults' => 
           [
            'label'        => 'Divider',
            'field_config' => 
             [
              'style'   => 'solid',
              'spacing' => 'normal',
            ],
          ],
        ],
        'html' => 
         [
          'icon'                   => 'o-code-bracket',
          'category'               => 'layout',
          'has_options'            => false,
          'supports_placeholder'   => false,
          'supports_default_value' => false,
          'is_layout'              => true,
          'validation_options'     => 
           [
          ],
          'label'    => 'Custom HTML',
          'defaults' => 
           [
            'label'        => 'Custom Content',
            'field_config' => 
             [
              'content' => '',
            ],
          ],
        ],
      ],
      'widths' => 
       [
        'full' => 
         [
          'label' => 'Full Width',
          'class' => 'w-full',
        ],
        'half' => 
         [
          'label' => 'Half Width',
          'class' => 'w-1/2',
        ],
        'third' => 
         [
          'label' => 'One Third',
          'class' => 'w-1/3',
        ],
        'two-thirds' => 
         [
          'label' => 'Two Thirds',
          'class' => 'w-2/3',
        ],
      ],
      'rules' => 
       [
        'text' => 
         [
          'optional' => 
           [
            0 => 'nullable',
            1 => 'string',
            2 => 'min:2',
            3 => 'max:10',
            4 => 'regex:/^a/',
          ],
          'required' => 
           [
            0 => 'required',
            1 => 'string',
            2 => 'min:2',
            3 => 'max:10',
            4 => 'regex:/^a/',
          ],
        ],
        'email' => 
         [
          'optional' => 
           [
            0 => 'nullable',
            1 => 'email',
          ],
          'required' => 
           [
            0 => 'required',
            1 => 'email',
          ],
        ],
        'url' => 
         [
          'optional' => 
           [
            0 => 'nullable',
            1 => 'url:http,https',
          ],
          'required' => 
           [
            0 => 'required',
            1 => 'url:http,https',
          ],
        ],
        'number' => 
         [
          'optional' => 
           [
            0 => 'nullable',
            1 => 'numeric',
            2 => 'min:0',
            3 => 'max:5',
          ],
          'required' => 
           [
            0 => 'required',
            1 => 'numeric',
            2 => 'min:0',
            3 => 'max:5',
          ],
        ],
        'phone' => 
         [
          'optional' => 
           [
            0 => 'nullable',
            1 => 'string',
          ],
          'required' => 
           [
            0 => 'required',
            1 => 'string',
          ],
        ],
        'textarea' => 
         [
          'optional' => 
           [
            0 => 'nullable',
            1 => 'string',
          ],
          'required' => 
           [
            0 => 'required',
            1 => 'string',
          ],
        ],
        'hidden' => 
         [
          'optional' => 
           [
            0 => 'nullable',
            1 => 'string',
          ],
          'required' => 
           [
            0 => 'required',
            1 => 'string',
          ],
        ],
        'select' => 
         [
          'optional' => 
           [
            0 => 'nullable',
            1 => 'string',
          ],
          'required' => 
           [
            0 => 'required',
            1 => 'string',
          ],
        ],
        'radio' => 
         [
          'optional' => 
           [
            0 => 'nullable',
            1 => 'string',
          ],
          'required' => 
           [
            0 => 'required',
            1 => 'string',
          ],
        ],
        'checkbox' => 
         [
          'optional' => 
           [
            0 => 'nullable',
            1 => 'boolean',
          ],
          'required' => 
           [
            0 => 'accepted',
            1 => 'boolean',
          ],
        ],
        'checkbox_group' => 
         [
          'optional' => 
           [
            0 => 'nullable',
            1 => 'array',
            2 => 'min:1',
          ],
          'required' => 
           [
            0 => 'required',
            1 => 'array',
            2 => 'min:1',
          ],
        ],
        'select_multiple' => 
         [
          'optional' => 
           [
            0 => 'nullable',
            1 => 'array',
          ],
          'required' => 
           [
            0 => 'required',
            1 => 'array',
          ],
        ],
        'file' => 
         [
          'optional' => 
           [
            0 => 'nullable',
            1 => 'file',
            2 => 'mimes:pdf',
            3 => 'max:100',
          ],
          'required' => 
           [
            0 => 'required',
            1 => 'file',
            2 => 'mimes:pdf',
            3 => 'max:100',
          ],
        ],
        'date' => 
         [
          'optional' => 
           [
            0 => 'nullable',
            1 => 'date',
            2 => 'after_or_equal:2026-01-01',
            3 => 'before_or_equal:2026-12-31',
          ],
          'required' => 
           [
            0 => 'required',
            1 => 'date',
            2 => 'after_or_equal:2026-01-01',
            3 => 'before_or_equal:2026-12-31',
          ],
        ],
        'time' => 
         [
          'optional' => 
           [
            0 => 'nullable',
            1 => 'regex:/^([01]\\d|2[0-3]):[0-5]\\d(:[0-5]\\d)?$/',
            2 => 'after_or_equal:09:00',
            3 => 'before_or_equal:17:00',
          ],
          'required' => 
           [
            0 => 'required',
            1 => 'regex:/^([01]\\d|2[0-3]):[0-5]\\d(:[0-5]\\d)?$/',
            2 => 'after_or_equal:09:00',
            3 => 'before_or_equal:17:00',
          ],
        ],
        'heading' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
          ],
        ],
        'paragraph' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
          ],
        ],
        'divider' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
          ],
        ],
        'html' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
          ],
        ],
      ],
      'svcrules' => 
       [
        'text' => 
         [
          'optional' => 
           [
            'formData.text' => 
             [
              0 => 'nullable',
              1 => 'string',
              2 => 'min:2',
              3 => 'max:10',
              4 => 'regex:/^a/',
            ],
          ],
          'required' => 
           [
            'formData.text' => 
             [
              0 => 'required',
              1 => 'string',
              2 => 'min:2',
              3 => 'max:10',
              4 => 'regex:/^a/',
            ],
          ],
        ],
        'email' => 
         [
          'optional' => 
           [
            'formData.email' => 
             [
              0 => 'nullable',
              1 => 'email',
            ],
          ],
          'required' => 
           [
            'formData.email' => 
             [
              0 => 'required',
              1 => 'email',
            ],
          ],
        ],
        'url' => 
         [
          'optional' => 
           [
            'formData.url' => 
             [
              0 => 'nullable',
              1 => 'url:http,https',
            ],
          ],
          'required' => 
           [
            'formData.url' => 
             [
              0 => 'required',
              1 => 'url:http,https',
            ],
          ],
        ],
        'number' => 
         [
          'optional' => 
           [
            'formData.number' => 
             [
              0 => 'nullable',
              1 => 'numeric',
              2 => 'min:0',
              3 => 'max:5',
            ],
          ],
          'required' => 
           [
            'formData.number' => 
             [
              0 => 'required',
              1 => 'numeric',
              2 => 'min:0',
              3 => 'max:5',
            ],
          ],
        ],
        'phone' => 
         [
          'optional' => 
           [
            'formData.phone' => 
             [
              0 => 'nullable',
              1 => 'string',
            ],
          ],
          'required' => 
           [
            'formData.phone' => 
             [
              0 => 'required',
              1 => 'string',
            ],
          ],
        ],
        'textarea' => 
         [
          'optional' => 
           [
            'formData.textarea' => 
             [
              0 => 'nullable',
              1 => 'string',
            ],
          ],
          'required' => 
           [
            'formData.textarea' => 
             [
              0 => 'required',
              1 => 'string',
            ],
          ],
        ],
        'hidden' => 
         [
          'optional' => 
           [
            'formData.hidden' => 
             [
              0 => 'nullable',
              1 => 'string',
            ],
          ],
          'required' => 
           [
            'formData.hidden' => 
             [
              0 => 'required',
              1 => 'string',
            ],
          ],
        ],
        'select' => 
         [
          'optional' => 
           [
            'formData.select' => 
             [
              0 => 'nullable',
              1 => 'string',
            ],
          ],
          'required' => 
           [
            'formData.select' => 
             [
              0 => 'required',
              1 => 'string',
            ],
          ],
        ],
        'radio' => 
         [
          'optional' => 
           [
            'formData.radio' => 
             [
              0 => 'nullable',
              1 => 'string',
            ],
          ],
          'required' => 
           [
            'formData.radio' => 
             [
              0 => 'required',
              1 => 'string',
            ],
          ],
        ],
        'checkbox' => 
         [
          'optional' => 
           [
            'formData.checkbox' => 
             [
              0 => 'nullable',
              1 => 'boolean',
            ],
          ],
          'required' => 
           [
            'formData.checkbox' => 
             [
              0 => 'accepted',
              1 => 'boolean',
            ],
          ],
        ],
        'checkbox_group' => 
         [
          'optional' => 
           [
            'formData.checkbox_group' => 
             [
              0 => 'nullable',
              1 => 'array',
              2 => 'min:1',
            ],
          ],
          'required' => 
           [
            'formData.checkbox_group' => 
             [
              0 => 'required',
              1 => 'array',
              2 => 'min:1',
            ],
          ],
        ],
        'select_multiple' => 
         [
          'optional' => 
           [
            'formData.select_multiple' => 
             [
              0 => 'nullable',
              1 => 'array',
            ],
          ],
          'required' => 
           [
            'formData.select_multiple' => 
             [
              0 => 'required',
              1 => 'array',
            ],
          ],
        ],
        'file' => 
         [
          'optional' => 
           [
            'files.file' => 
             [
              0 => 'nullable',
              1 => 'file',
              2 => 'mimes:pdf',
              3 => 'max:100',
            ],
          ],
          'required' => 
           [
            'files.file' => 
             [
              0 => 'required',
              1 => 'file',
              2 => 'mimes:pdf',
              3 => 'max:100',
            ],
          ],
        ],
        'date' => 
         [
          'optional' => 
           [
            'formData.date' => 
             [
              0 => 'nullable',
              1 => 'date',
              2 => 'after_or_equal:2026-01-01',
              3 => 'before_or_equal:2026-12-31',
            ],
          ],
          'required' => 
           [
            'formData.date' => 
             [
              0 => 'required',
              1 => 'date',
              2 => 'after_or_equal:2026-01-01',
              3 => 'before_or_equal:2026-12-31',
            ],
          ],
        ],
        'time' => 
         [
          'optional' => 
           [
            'formData.time' => 
             [
              0 => 'nullable',
              1 => 'regex:/^([01]\\d|2[0-3]):[0-5]\\d(:[0-5]\\d)?$/',
              2 => 'after_or_equal:09:00',
              3 => 'before_or_equal:17:00',
            ],
          ],
          'required' => 
           [
            'formData.time' => 
             [
              0 => 'required',
              1 => 'regex:/^([01]\\d|2[0-3]):[0-5]\\d(:[0-5]\\d)?$/',
              2 => 'after_or_equal:09:00',
              3 => 'before_or_equal:17:00',
            ],
          ],
        ],
        'heading' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
          ],
        ],
        'paragraph' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
          ],
        ],
        'divider' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
          ],
        ],
        'html' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
          ],
        ],
      ],
      'messages' => 
       [
        'text' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
            'formData.text.required' => 'The Text field is required.',
          ],
        ],
        'email' => 
         [
          'optional' => 
           [
            'formData.email.email' => 'Please enter a valid email address for Email.',
          ],
          'required' => 
           [
            'formData.email.required' => 'The Email field is required.',
            'formData.email.email'    => 'Please enter a valid email address for Email.',
          ],
        ],
        'url' => 
         [
          'optional' => 
           [
            'formData.url.url' => 'Please enter a valid URL for Url.',
          ],
          'required' => 
           [
            'formData.url.required' => 'The Url field is required.',
            'formData.url.url'      => 'Please enter a valid URL for Url.',
          ],
        ],
        'number' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
            'formData.number.required' => 'The Number field is required.',
          ],
        ],
        'phone' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
            'formData.phone.required' => 'The Phone field is required.',
          ],
        ],
        'textarea' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
            'formData.textarea.required' => 'The Textarea field is required.',
          ],
        ],
        'hidden' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
            'formData.hidden.required' => 'The Hidden field is required.',
          ],
        ],
        'select' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
            'formData.select.required' => 'The Select field is required.',
          ],
        ],
        'radio' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
            'formData.radio.required' => 'The Radio field is required.',
          ],
        ],
        'checkbox' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
            'formData.checkbox.accepted' => 'The Checkbox field is required.',
          ],
        ],
        'checkbox_group' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
            'formData.checkbox_group.required' => 'The Checkbox_group field is required.',
          ],
        ],
        'select_multiple' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
            'formData.select_multiple.required' => 'The Select_multiple field is required.',
          ],
        ],
        'file' => 
         [
          'optional' => 
           [
            'files.file.file'  => 'Please upload a valid file for File.',
            'files.file.mimes' => 'The File must be a file of the allowed types.',
            'files.file.max'   => 'The File must not exceed the maximum file size.',
          ],
          'required' => 
           [
            'files.file.required' => 'The File field is required.',
            'files.file.file'     => 'Please upload a valid file for File.',
            'files.file.mimes'    => 'The File must be a file of the allowed types.',
            'files.file.max'      => 'The File must not exceed the maximum file size.',
          ],
        ],
        'date' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
            'formData.date.required' => 'The Date field is required.',
          ],
        ],
        'time' => 
         [
          'optional' => 
           [
            'formData.time.regex' => 'Please enter a valid time for Time.',
          ],
          'required' => 
           [
            'formData.time.required' => 'The Time field is required.',
            'formData.time.regex'    => 'Please enter a valid time for Time.',
          ],
        ],
        'heading' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
          ],
        ],
        'paragraph' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
          ],
        ],
        'divider' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
          ],
        ],
        'html' => 
         [
          'optional' => 
           [
          ],
          'required' => 
           [
          ],
        ],
      ],
    ];
