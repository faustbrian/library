<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | This value determines which storage disk will be used for storing your
    | archive media files. By default, it uses your application's default
    | filesystem disk, but you may specify any disk defined in the
    | filesystems configuration file to suit your requirements.
    |
    */

    'disk' => env('ARCHIVE_DISK', config('filesystems.default')),

    /*
    |--------------------------------------------------------------------------
    | Storage Path Prefix
    |--------------------------------------------------------------------------
    |
    | This option controls the directory prefix where archive files will be
    | stored within your configured disk. The default 'media' prefix keeps
    | your archive files organized and separate from other application
    | files. You may customize this to any valid directory name.
    |
    */

    'prefix' => env('ARCHIVE_PREFIX', 'media'),

    /*
    |--------------------------------------------------------------------------
    | Path Generator
    |--------------------------------------------------------------------------
    |
    | The path generator determines how file paths are structured when files
    | are stored in your archive. The default generator creates organized
    | directory structures, but you may implement your own path generator
    | by creating a class that matches the PathGenerator interface.
    |
    */

    'path_generator' => \Cline\Archive\Storage\PathGenerator\DefaultPathGenerator::class,

    /*
    |--------------------------------------------------------------------------
    | URL Generator
    |--------------------------------------------------------------------------
    |
    | The URL generator is responsible for creating accessible URLs for your
    | archive files. The default generator works with Laravel's filesystem
    | configuration to generate appropriate URLs, but you may provide a
    | custom generator to match your specific URL structure needs.
    |
    */

    'url_generator' => \Cline\Archive\Support\UrlGenerator\DefaultUrlGenerator::class,

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size
    |--------------------------------------------------------------------------
    |
    | This value defines the maximum file size (in bytes) that can be uploaded
    | to the archive. The default is 10MB (10485760 bytes). You should also
    | ensure your server's PHP configuration (upload_max_filesize and
    | post_max_size) allows files of this size to be uploaded.
    |
    */

    'max_file_size' => 1024 * 1024 * 10, // 10MB

    /*
    |--------------------------------------------------------------------------
    | Primary Key Type
    |--------------------------------------------------------------------------
    |
    | This option specifies the type of primary key used for archive models.
    | The default 'id' type uses auto-incrementing integers, suitable for
    | most applications. You may change this to 'uuid' if your application
    | requires universally unique identifiers for archive records.
    |
    */

    'primary_key_type' => env('ARCHIVE_PRIMARY_KEY', 'id'),

];

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
// Here endeth thy configuration, noble developer!                            //
// Beyond: code so wretched, even wyrms learned the scribing arts.            //
// Forsooth, they but penned "// TODO: remedy ere long"                       //
// Three realms have fallen since...                                          //
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
//                                                  .~))>>                    //
//                                                 .~)>>                      //
//                                               .~))))>>>                    //
//                                             .~))>>             ___         //
//                                           .~))>>)))>>      .-~))>>         //
//                                         .~)))))>>       .-~))>>)>          //
//                                       .~)))>>))))>>  .-~)>>)>              //
//                   )                 .~))>>))))>>  .-~)))))>>)>             //
//                ( )@@*)             //)>))))))  .-~))))>>)>                 //
//              ).@(@@               //))>>))) .-~))>>)))))>>)>               //
//            (( @.@).              //))))) .-~)>>)))))>>)>                   //
//          ))  )@@*.@@ )          //)>))) //))))))>>))))>>)>                 //
//       ((  ((@@@.@@             |/))))) //)))))>>)))>>)>                    //
//      )) @@*. )@@ )   (\_(\-\b  |))>)) //)))>>)))))))>>)>                   //
//    (( @@@(.@(@ .    _/`-`  ~|b |>))) //)>>)))))))>>)>                      //
//     )* @@@ )@*     (@)  (@) /\b|))) //))))))>>))))>>                       //
//   (( @. )@( @ .   _/  /    /  \b)) //))>>)))))>>>_._                       //
//    )@@ (@@*)@@.  (6///6)- / ^  \b)//))))))>>)))>>   ~~-.                   //
// ( @jgs@@. @@@.*@_ VvvvvV//  ^  \b/)>>))))>>      _.     `bb                //
//  ((@@ @@@*.(@@ . - | o |' \ (  ^   \b)))>>        .'       b`,             //
//   ((@@).*@@ )@ )   \^^^/  ((   ^  ~)_        \  /           b `,           //
//     (@@. (@@ ).     `-'   (((   ^    `\ \ \ \ \|             b  `.         //
//       (*.@*              / ((((        \| | |  \       .       b `.        //
//                         / / (((((  \    \ /  _.-~\     Y,      b  ;        //
//                        / / / (((((( \    \.-~   _.`" _.-~`,    b  ;        //
//                       /   /   `(((((()    )    (((((~      `,  b  ;        //
//                     _/  _/      `"""/   /'                  ; b   ;        //
//                 _.-~_.-~           /  /'                _.'~bb _.'         //
//               ((((~~              / /'              _.'~bb.--~             //
//                                  ((((          __.-~bb.-~                  //
//                                              .'  b .~~                     //
//                                              :bb ,'                        //
//                                              ~~~~                          //
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
