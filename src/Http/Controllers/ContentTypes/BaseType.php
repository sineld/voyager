<?php

namespace TCG\Voyager\Http\Controllers\ContentTypes;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use TCG\Voyager\Support\SafeUpload;

abstract class BaseType
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var
     */
    protected $slug;

    /**
     * @var
     */
    protected $row;

    /**
     * @var
     */
    protected $options;

    /**
     * Password constructor.
     *
     * @param Request $request
     * @param $slug
     * @param $row
     */
    public function __construct(Request $request, $slug, $row, $options)
    {
        $this->request = $request;
        $this->slug = $slug;
        $this->row = $row;
        $this->options = $options;
    }

    /**
     * @return mixed
     */
    abstract public function handle();

    /**
     * Refuse uploads the web server could execute once stored (CVE-2024-55417).
     *
     * The stored name keeps the client's extension, so the client's file name is checked.
     *
     * @param UploadedFile[] $files
     *
     * @throws ValidationException
     */
    protected function ensureAllowedFileNames(array $files)
    {
        foreach ($files as $file) {
            if (!SafeUpload::isAllowedFileName($file->getClientOriginalName())) {
                throw ValidationException::withMessages([
                    $this->row->field => __('voyager::media.error_extension_not_allowed'),
                ]);
            }
        }
    }
}
