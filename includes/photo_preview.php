<?php
$photosRequired = !empty($photos_required);
?>
<div class="photo-uploader">
    <label class="photo-dropzone" id="photoDropzone" for="photos" role="button" tabindex="0" aria-label="Добавить фотографии">
        <svg class="photo-dropzone-icon" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="m21 15-4.5-4.5L9 18"/></svg>
        <span class="photo-dropzone-title">Добавить фотографии</span>
        <span class="photo-dropzone-hint">JPG, PNG или WebP, до 5 МБ каждый, до 8 штук<span class="photo-dropzone-count" id="photoCount"></span></span>
    </label>
    <div class="photo-previews" id="photoPreviews" hidden></div>
    <input type="file" id="photos" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="photo-input-sr"<?= $photosRequired ? ' data-required="1"' : '' ?>>
</div>
