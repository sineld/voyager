<?php

namespace TCG\Voyager\Http\Controllers\ContentTypes;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use TCG\Voyager\Support\ImageFactory;


class MultipleImage extends BaseType
{
    /**
     * @return string
     */
    public function handle()
    {
        $filesPath = [];
        $files = $this->request->file($this->row->field);

        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            if (!$file->isValid()) {
                continue;
            }

            $manager = ImageFactory::make();
            $image = $manager->read($file->getPathname())->orient();

            $resize_width = null;
            $resize_height = null;

            if (isset($this->options->resize) && (
                isset($this->options->resize->width) || isset($this->options->resize->height)
            )) {
                if (isset($this->options->resize->width)) {
                    $resize_width = intval($this->options->resize->width);
                }
                if (isset($this->options->resize->height)) {
                    $resize_height = intval($this->options->resize->height);
                }
            } else {
                $resize_width = $image->width();
                $resize_height = $image->height();
            }

            // Ensure both dimensions are set and greater than 0
            if (!$resize_width || $resize_width <= 0) {
                $resize_width = $image->width();
            }
            if (!$resize_height || $resize_height <= 0) {
                $resize_height = $image->height();
            }

            $resize_quality = intval($this->options->quality ?? 75);

            $filename = Str::random(20);
            $path = $this->slug.DIRECTORY_SEPARATOR.date('FY').DIRECTORY_SEPARATOR;
            array_push($filesPath, $path.$filename.'.'.$file->getClientOriginalExtension());
            $filePath = $path.$filename.'.'.$file->getClientOriginalExtension();

            $image = $image->resize($resize_width, $resize_height)->encodeByExtension($file->getClientOriginalExtension(), $resize_quality);

            Storage::disk(config('voyager.storage.disk'))->put($filePath, (string) $image, 'public');

            if (isset($this->options->thumbnails)) {
                foreach ($this->options->thumbnails as $thumbnails) {
                    if (isset($thumbnails->name) && isset($thumbnails->scale)) {
                        $scale = intval($thumbnails->scale) / 100;
                        $thumb_resize_width = $resize_width;
                        $thumb_resize_height = $resize_height;

                        if ($thumb_resize_width != null && $thumb_resize_width != 'null') {
                            $thumb_resize_width = intval($thumb_resize_width * $scale);
                        }

                        if ($thumb_resize_height != null && $thumb_resize_height != 'null') {
                            $thumb_resize_height = intval($thumb_resize_height * $scale);
                        }

                        // Create a new image instance for thumbnails to avoid using encoded image
                        $thumb_image = $manager->read($file->getPathname());
                        
                        // Ensure both dimensions are set and greater than 0 for thumbnails
                        if (!$thumb_resize_width || $thumb_resize_width <= 0) {
                            $thumb_resize_width = $thumb_image->width();
                        }
                        if (!$thumb_resize_height || $thumb_resize_height <= 0) {
                            $thumb_resize_height = $thumb_image->height();
                        }
                        
                        $thumb_image = $thumb_image
                            ->orient()
                            ->resize($thumb_resize_width, $thumb_resize_height)
                            ->encodeByExtension($file->getClientOriginalExtension(), $resize_quality);
                    } elseif (isset($this->options->thumbnails) && isset($thumbnails->crop->width) && isset($thumbnails->crop->height)) {
                        $crop_width = $thumbnails->crop->width;
                        $crop_height = $thumbnails->crop->height;
                        $thumb_image = $manager->read($file->getPathname())
                            ->orient()
                            ->resize($crop_width, $crop_height)
                            ->encodeByExtension($file->getClientOriginalExtension(), $resize_quality);
                    }

                    Storage::disk(config('voyager.storage.disk'))->put(
                        $path.$filename.'-'.$thumbnails->name.'.'.$file->getClientOriginalExtension(),
                        (string) $thumb_image,
                        'public'
                    );
                }
            }
        }

        return json_encode($filesPath);
    }
}
