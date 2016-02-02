<?php
namespace WpImage;

use Helper\StringH;

class WpImage
{
    private $mimesExtensions = [
        'image/jpeg' => ['jpg', 'jpeg', 'jpe'],
        'image/gif' => ['gif'],
        'image/png' => ['png'],
        'image/bmp' => ['bmp'],
        'image/tiff' => ['tif', 'tiff'],
        'image/x-icon' => ['ico']
    ];
    private $extensions = [];
    private $mimeValidate;
    private $img;
    protected $isUrl;
    protected $mime;
    protected $extension;
    protected $originBaseName;
    protected $originPathName;
    protected $size;
    protected $name;
    protected $uploadDir;
    protected $onlyName;
    protected $fullPath;
    protected $attachmentId;
    protected $attachmentMetadata;
    protected $postId;
    protected $postMetaId;
    protected $isThumbnail = false;
    private $imgBuffer;

    public function __construct($image, $validateMime = null, $sanitizeName = true)
    {
        $this->img = new \SplFileInfo($image);
        $this->isUrl = true;
        if (!isset(parse_url($image)['scheme'])) {
            if ($this->img->isFile() === false) {
                throw new \Exception('invalid image');
            }
            $this->isUrl = false;
        }

        $this->validateImage($validateMime);
        $this->setName(null, $sanitizeName);
    }

    /**
     * @return isUrl
     */
    public function getIsUrl()
    {
        return $this->isUrl;
    }

    /**
     * @return mime
     */
    public function getMime()
    {
        return $this->mime;
    }

    /**
     * @return extension
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * @return originBaseName
     */
    public function getOriginBaseName()
    {
        return $this->originBaseName;
    }

    /**
     * @return originPathName
     */
    public function getOriginPathName()
    {
        return $this->originPathName;
    }

    /**
     * @return size
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @return name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return uploadDir
     */
    public function getUploadDir()
    {
        return $this->uploadDir;
    }

    /**
     * @return onlyName
     */
    public function getOnlyName()
    {
        return $this->onlyName;
    }

    /**
     * @return fullPath
     */
    public function getFullPath()
    {
        return $this->fullPath;
    }

    /**
     * @return attachmentId
     */
    public function getAttachmentId()
    {
        return $this->attachmentId;
    }

    /**
     * @return attachmentMetadata
     */
    public function getAttachmentMetadata()
    {
        return $this->attachmentMetadata;
    }

    /**
     * @return postId
     */
    public function getPostId()
    {
        return $this->postId;
    }

    /**
     * @return postMetaId
     */
    public function getPostMetaId()
    {
        return $this->postMetaId;
    }

    /**
     * @return isThumbnail
     */
    public function getIsThumnail()
    {
        return $this->isThumbnail;
    }

    private function validateImage($validateMime)
    {
        $this->setMimeValidate($validateMime);
        $this->validateExtension();
        $this->validateMimeType();
    }

    private function setMimeValidate($mime)
    {
        if ($mime !== null) {
            if (!array_key_exists($mime, $this->mimesExtensions)) {
                throw new \Exception('Mime image invalid');
            }
            $this->mimeValidate = [$mime];
        } else {
            $this->mimeValidate = array_keys($this->mimesExtensions);
        }
    }

    private function setExtensions()
    {
        if (count($this->mimeValidate) > 1) {
            $extensions = array_values($this->mimesExtensions);
            foreach ($extensions as $e) {
                 $this->extensions = array_merge($this->extensions, $e);
            }
        } else {
            $this->extensions = $this->mimesExtensions[$this->mimeValidate[0]];
        }
    }

    private function validateExtension()
    {
        $this->setExtensions();
        if (!in_array($this->img->getExtension(), $this->extensions)) {
            throw new \Exception('invalid extension');
        }
        $this->setSplFileInfoData();
    }

    private function setSplFileInfoData()
    {
        $this->extension = $this->img->getExtension();
        $this->originBaseName = $this->img->getBasename();
        if ($this->isUrl === false) {
            $this->size = $this->img->getSize();
        }
        $this->originPathName = $this->img->getPathname();
    }

    private function validateMimeType()
    {
        $this->imgBuffer = file_get_contents($this->originPathName);
        if ($this->imgBuffer === false) {
            throw new \Exception('error file_get_contents');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($this->imgBuffer);
        if (!in_array($mime, $this->mimeValidate)) {
            throw new \Exception('invalid mimetype');
        }
        $this->mime = $mime;
        unset($finfo);
    }

    public function setUploadDir($pathUploadDir = null)
    {
        if ($pathUploadDir === null) {
            $this->uploadDir = wp_upload_dir()['path'];
        } else {
            $path = realpath($pathUploadDir);
            if ($path === false) {
                throw new \Exception('Invalid path upload dir');
            }
            $this->uploadDir = $path;
        }
        $this->createDir();
    }

    private function createDir()
    {
         return wp_mkdir_p($this->uploadDir);
    }

    public function setName($imgName = null, $sanitizeName = true)
    {
        if ($imgName === null ) {
            $imgName = str_replace('.'. $this->extension, '', $this->originBaseName);
        }
        $this->onlyName = ($sanitizeName === true)
            ? StringH::slug($imgName)
            : $imgName;
        $this->name = $this->onlyName . '.'. $this->extension;
    }

    private function setFullPath()
    {
        if ($this->uploadDir === null) {
            $this->setUploadDir();
        }
        $this->fullPath = $this->uploadDir . DIRECTORY_SEPARATOR . $this->name;
    }

    public function save()
    {
        $this->setFullPath();
        $r = ($this->isUrl === true)
            ? file_put_contents($this->fullPath, $this->imgBuffer)
            : copy($this->originPathName, $this->fullPath);
        unset($this->imgBuffer);
        return ($r === false) ? false : true;
    }

    public function move()
    {
        if ($this->isUrl === true) {
            throw new \Exception("you can't move an external image");
        }
        $this->setFullPath();
        return rename($this->originPathName, $this->fullPath);
    }

    private function setPostId($postId)
    {
        $postId = (int)$postId;
        if ($postId == 0) {
            throw new \Exception('postId invalid');
        }
        $this->postId = $postId;
    }

    private function setAttachmentMetadata()
    {
        $this->attachmentMetadata = wp_generate_attachment_metadata(
            $this->attachmentId,
            $this->fullPath
        );
        if ($this->attachmentMetadata === false) {
            $data = ' attachmentId: '. $this->attachmentId .' ,  fullPath: '. $this->fullPath;
            throw new \Exception('failed generate attachment metadata'. $data);
        }
        $this->updateAttachmentMetadata();
    }

    private function updateAttachmentMetadata()
    {
        $r = wp_update_attachment_metadata(
            $this->attachmentId,
            $this->attachmentMetadata
        );
        if ($r === false) {
            throw new \Exception('failed update attachment metadata'. $data);
        }
    }

    private function setPostThumbnail()
    {
        $thumb = set_post_thumbnail($this->postId, $this->attachmentId);
        if ($thumb === false) {
            throw new \Exception('error set post thumbnail');
        }
        $this->isThumbnail = true;
    }

    public function insertAttachment($postId, $thumbnail = false, $postTitle = null)
    {
        $this->setPostId($postId);
        $title = ($postTitle === null) ? $this->onlyName : $postTitle;
        $attachment = [
            'post_mime_type' => $this->mime,
            'post_title' => sanitize_title($title),
            'post_content' => '',
            'post_status' => 'inherit'
        ];
        if ($this->fullPath === null) {
            $this->setFullPath();
        }
        $this->attachmentId = wp_insert_attachment(
            $attachment,
            $this->fullPath,
            $this->postId
        );
        if ($this->attachmentId == 0) {
            throw new \Exception('error insert attachment');
        }
        $this->setAttachmentMetadata();
        if ($thumbnail === true) {
            $this->setPostThumbnail();
        }
    }
}
