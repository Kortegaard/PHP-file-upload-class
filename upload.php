<?php
/**
 * Simple PHP upload class
 * https://github.com/aivis/PHP-file-upload-class
 * @author Aivis Silins
 */
class Upload {

    /**
     * Default directory persmissions (destination dir)
     */
    protected $default_permissions = 0750;


    /**
     * File post array
     *
     * @var array
     */
    protected $file_post = array();


    /**
     * Destination directory
     *
     * @var string
     */
    protected $destination;


    /**
     * Fileinfo
     *
     * @var object
     */
    protected $finfo;


    /**
     * Data about file
     *
     * @var array
     */
    public $files = array();


    /**
     * Max. file size
     *
     * @var int
     */
    protected $max_file_size;


    /**
     * Allowed mime types
     *
     * @var array
     */
    protected $mimes = array();


    /**
     * External callback object
     *
     * @var obejct
     */
    protected $external_callback_object;


    /**
     * External callback methods
     *
     * @var array
     */
    protected $external_callback_methods = array();


    /**
     * Temp path
     *
     * @var string
     */
    protected $tmp_name;


    /**
     * Validation errors
     *
     * @var array
     */
    protected $validation_errors = array();


    /**
     * Filename (new)
     *
     * @var string
     */
    protected $filename;


    /**
     * Internal callbacks (filesize check, mime, etc)
     *
     * @var array
     */
    private $callbacks = array();

    /**
     * Root dir
     *
     * @var string
     */
    protected $root;


    protected $mime_types = array(

        'txt' => 'text/plain', 'htm' => 'text/html', 'html' => 'text/html', 'php' => 'text/html', 'css' => 'text/css', 'js' => 'application/javascript', 'json' => 'application/json', 'xml' => 'application/xml', 'swf' => 'application/x-shockwave-flash', 'flv' => 'video/x-flv',

        // images
        'png' => 'image/png', 'jpe' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'jpg' => 'image/jpeg', 'gif' => 'image/gif', 'bmp' => 'image/bmp', 'ico' => 'image/vnd.microsoft.icon', 'tiff' => 'image/tiff', 'tif' => 'image/tiff', 'svg' => 'image/svg+xml', 'svgz' => 'image/svg+xml',

        // archives
        'zip' => 'application/zip', 'rar' => 'application/x-rar-compressed', 'exe' => 'application/x-msdownload', 'msi' => 'application/x-msdownload', 'cab' => 'application/vnd.ms-cab-compressed',

        // audio/video
        'mp3' => 'audio/mpeg', 'qt' => 'video/quicktime', 'mov' => 'video/quicktime',

        // adobe
        'pdf' => 'application/pdf', 'psd' => 'image/vnd.adobe.photoshop', 'ai' => 'application/postscript', 'eps' => 'application/postscript', 'ps' => 'application/postscript',

        // ms office
        'doc' => 'application/msword', 'rtf' => 'application/rtf', 'xls' => 'application/vnd.ms-excel', 'ppt' => 'application/vnd.ms-powerpoint',

        // open office
        'odt' => 'application/vnd.oasis.opendocument.text', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet',);


    /**
     * Return upload object
     *
     * $destination		= 'path/to/your/file/destination/folder';
     *
     * @param string $destination
     * @param string $root
     * @return Upload
     */
    public static function factory($destination, $root = false) {
        return new Upload($destination, $root);

    }


    /**
     *  Define ROOT constant and set & create destination path
     *
     * @param string $destination
     * @param string $root
     */
    public function __construct($destination, $root = false) {

        if ($root) {

            $this->root = $root;

        } else {

            $this->root = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR;
        }

        // set & create destination path
        if (!$this->set_destination($destination)) {

            throw new Exception('Upload: Can\'t create destination. '.$this->root . $this->destination);

        }

        //create finfo object
        $this->finfo = new \finfo();

    }

    public function set_root($root){
        $this->root = $root;
    }

    /**
     * Set target filename
     *
     * @param string $filename
     */
    public function set_filename($filename) {
        $this->filename = $filename;
    }

    /**
     * Check & Save file
     *
     * Return data about current upload
     *
     * @return array
     */
    public function upload($filename = '') {

        $this->set_filename($filename);
        if ($this->check()) {
            $this->save();
        }

        // return state data
        return $this->get_state();

    }


    /**
     * Save file on server
     *
     * Return state data
     *
     * @return array
     */
    public function save() {

        $this->save_file();

        return $this->get_state();

    }


    /**
     * Validate file (execute callbacks)
     *
     * Returns TRUE if validation successful
     *
     * @return bool
     */
    public function check() {

        //execute callbacks (check filesize, mime, also external callbacks
        $this->validate();

        //add error messages
        //$this->files['errors'] = $this->get_errors();

        //change file validation status
        //$this->files['status'] = empty($this->validation_errors);

        return empty($this->validation_errors);

    }

    public function status(){
        return empty($this->validation_errors);
    }

    /**
     * Get current state data
     *
     * @return array
     */
    public function get_state() {

        return $this->files;

    }


    /**
     * Save file on server
     */
    protected function save_file() {
        $files = $this->files;
        foreach ($files as $i => $file){
            if (!is_numeric($i)) { continue; }
            //create & set new filename
            if(!isset($file["filename"]) || empty($file["filename"])){
                $files[$i]['filename'] = $this->create_new_filename($file);
            }

            //set filename
            //$this->files['filename']	= $this->filename;

            //set full path
            $files[$i]['full_path'] = $this->root . $this->destination . $this->filename;
            $files[$i]['path']      = $this->destination . $this->filename;

            $status = move_uploaded_file($files[$i]["tmp_name"], $files[$i]['full_path']);

            //checks whether upload successful
            if (!$status) {
                throw new Exception('Upload: Can\'t upload file.');
            }

            //done
            $files[$i]['status']	= true;
        }
        $this->files = $files;


    }


    /**
     * Set data about file
     */
    protected function set_file_data() {

        $files = array();
        foreach ($this->file_post as $file_post) {
            $file_size = $this->get_single_file_size($file_post);
            $files[] = array('status' => false,
                             'destination' => $this->destination,
                             'name' => $file_post["name"],
                             'size_in_bytes' => $file_size,
                             'size_in_mb' => $this->single_bytes_to_mb($file_size),
                             'mime' => $this->get_single_file_mime($file_post),
                             'original_filename' => $this->get_single_original_file_name($file_post),
                             'tmp_name' => $file_post["tmp_name"],
                             'post_data' => $this->file_post,);
        }
        $this->files = $files;

    }

    public function get_original_file_name(){
        return array_map(function($file){return $file['name'];}, $this->file_post);
    }

    public function get_single_original_file_name($file){
        return $file['name'];
    }

    /**
     * Set validation error
     *
     * @param string $message
     */
    public function set_error($message) {

        $this->validation_errors[] = $message;

    }


    /**
     * Return validation errors
     *
     * @return array
     */
    public function get_errors() {

        return $this->validation_errors;

    }


    /**
     * Set external callback methods
     *
     * @param object $instance_of_callback_object
     * @param array $callback_methods
     */
    public function callbacks($instance_of_callback_object, $callback_methods) {

        if (empty($instance_of_callback_object)) {

            throw new Exception('Upload: $instance_of_callback_object can\'t be empty.');

        }

        if (!is_array($callback_methods)) {

            throw new Exception('Upload: $callback_methods data type need to be array.');

        }

        $this->external_callback_object	 = $instance_of_callback_object;
        $this->external_callback_methods = $callback_methods;

    }


    /**
     * Execute callbacks
     */
    protected function validate() {

        //get curent errors
        $errors = $this->get_errors();
        if (empty($errors)) {

            //set data about current file
            $this->set_file_data();

            //execute internal callbacks
            $this->execute_callbacks($this->callbacks, $this);

            //execute external callbacks
            $this->execute_callbacks($this->external_callback_methods, $this->external_callback_object);
        }

    }


    /**
     * Execute callbacks
     */
    protected function execute_callbacks($callbacks, $object) {

        foreach($callbacks as $method) {

            $object->$method($this);

        }

    }


    /**
     * File mime type validation callback
     *
     * @param obejct $object
     */
    protected function check_mime_type($object) {
        if (empty($object->mimes)) { return; }

        foreach ($object->files as $file) {
            if (!in_array($file["mime"], $object->mimes)) {
                $object->set_error('Mime type not allowed.');
            }
        }

    }


    /**
     * Set allowed mime types
     *
     * @param array $mimes
     */
    public function set_allowed_mime_types($mimes) {

        $this->mimes		= $mimes;

        //if mime types is set -> set callback
        $this->callbacks[]	= 'check_mime_type';

    }

    /**
     * Allow file types, input could be something like ['jpg', 'png', 'pdf']
     *
     * @param $types_allowed
     */
    public function set_allowed_file_types($types_allowed){
        //mime only allowed keys
        $maskedArr = array_intersect_key($this->mime_types, array_flip($types_allowed));
        //Array of mimes
        $this->set_allowed_mime_types(array_values($maskedArr));
    }


    /**
     * File size validation callback
     *
     * @param object $object
     */
    protected function check_file_size($object) {

        if (!empty($object->max_file_size)) {

            $file_size_in_mb = $this->bytes_to_mb($object->file['size_in_bytes']);

            if ($object->max_file_size <= $file_size_in_mb) {

                $object->set_error('File is too big.');

            }

        }

    }


    /**
     * Set max. file size
     *
     * @param int $size
     */
    public function set_max_file_size($size) {

        $this->max_file_size	= $size;

        //if max file size is set -> set callback
        $this->callbacks[]	= 'check_file_size';

    }


    /**
     * Set File array to object
     *
     * TODO : can use check_single_file_array for checking
     *
     * @param array $file
     */
    public function file($file) {

        if (is_array($file["name"])){
            $files = array();
            foreach ($file["name"] as $i => $val){
                if (empty($file["name"][$i]))
                    continue;
                foreach (array_keys($file) as $key){
                    $files[$i][$key] = $file[$key][$i];
                }

            }

            $this->set_file_array($files);
        }else{
            $this->set_file_array([$file]);
        }
    }

    public function isMultipleFiles($file){
        return is_array($file["name"]);
    }

    public function get_original_file_extension($file){
        $tmp = explode(".", $file["name"]);
        $ending = end($tmp);
        return in_array($ending, array_keys($this->mime_types)) ? $ending : false;
    }


    /**
     * Set file array
     *
     * @param array $file
     */
    protected function set_file_array($files) {
        //checks whether file array is valid
        if (!$this->check_file_array($files)) {
            //file not selected or some bigger problems (broken files array)
            $this->set_error('Please select file.');

        }
        //set file data
        $this->file_post = $files;

        //set tmp path
        $this->set_tmp_name($files);

    }

    protected function set_tmp_name($file){
        $this->tmp_name = array_column($file, "tmp_name");
    }



/**/
    /**
     * Checks whether Files post array is valid
     *
     * @return bool
     */
    //changed
    protected function check_file_array($file) {
        foreach ($file as $val){
            if (!$this->check_single_file_array($val))
                return false;
        }
        return true;
    }

    protected function check_single_file_array($file){
        return isset($file['error'])
        && !empty($file['name'])
        && !empty($file['type'])
        && !empty($file['tmp_name'])
        && !empty($file['size']);
    }


    /**
     * Get file mime type
     *
     * @return string
     */
    protected function get_file_mimes() {
        return array_map(function($name){return (!empty($name)) ? $this->finfo->file($name, FILEINFO_MIME_TYPE) : "";}, $this->tmp_name);
    }

    protected function get_single_file_mime($file) {
        return $this->finfo->file($file['tmp_name'], FILEINFO_MIME_TYPE);
    }


    /**
     * Get file size
     *
     * @return int
     */
    protected function get_file_sizes() {
        return array_map(function($val){return filesize($val);} , $this->tmp_name);
    }

    protected function get_single_file_size($file) {
        return filesize($file['tmp_name']);
    }


    /**
     * Set destination path (return TRUE on success)
     *
     * @param string $destination
     * @return bool
     */
    protected function set_destination($destination) {

        $this->destination = $destination . DIRECTORY_SEPARATOR;

        return $this->destination_exist() ? TRUE : $this->create_destination();

    }


    /**
     * Checks whether destination folder exists
     *
     * @return bool
     */
    protected function destination_exist() {

        return is_writable($this->root . $this->destination);

    }


    /**
     * Create path to destination
     *
     * @param string $dir
     * @return bool
     */
    protected function create_destination() {

        return mkdir($this->root . $this->destination, $this->default_permissions, true);

    }


    /**
     * Set unique filename
     *
     * @return string
     */
    protected function create_new_filename($file) {
        $filename = sha1(mt_rand(1, 9999) . $this->destination . uniqid()) . time();
        $extension = $this->get_original_file_extension($file);
        $this->set_filename($filename . ($extension ? "." . $extension : ""));
        return $filename . ($extension ? "." . $extension : "");
    }


    /**
     * Convert bytes to mb.
     *
     * @param int $bytes
     * @return int
     */
    protected function bytes_to_mb($bytes) {
        return array_map(function($byte){return round(($byte / 1048576), 2);}, $bytes);

    }

    protected function single_bytes_to_mb($bytes) {
        return round(($bytes / 1048576), 2);

    }

} // end of Upload
