@props([
    'id',
    'model' => null,
    'name' => null,
    'value' => '',
])

@php($fieldName = $name ?: ($model ?: $id))

<div wire:ignore>
    <textarea
        id="{{ $id }}"
        name="{{ $fieldName }}"
        @if ($model) wire:model="{{ $model }}" @endif
    >{{ $value }}</textarea>
</div>

@once
    <script>
        window.initializeEmberTinyMce = function (editorId) {
            const initialize = () => {
                if (!window.tinymce) {
                    window.setTimeout(initialize, 50);
                    return;
                }

                const textarea = document.getElementById(editorId);

                if (!textarea) return;

                const currentEditor = window.tinymce.get(editorId);

                if (currentEditor) currentEditor.remove();

                window.tinymce.init({
                    selector: '#' + editorId,
                    plugins: 'advlist anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount code fullscreen insertdatetime help preview',
                    toolbar: 'undo redo | styles | addImage addVideo addBorderMerah addSlider | bold italic underline strikethrough forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | code removeformat | fullscreen preview',
                    extended_valid_elements: 'iframe[src|width|height|frameborder|allowfullscreen|style|class|loading|referrerpolicy]',
                    valid_elements: '*[*]',
                    valid_children: '+body[iframe]',
                    verify_html: false,
                    forced_root_block: 'p',
                    allow_html_in_named_anchor: true,
                    sandbox_iframes: false,
                    content_style: 'body { font-family: Instrument Sans, sans-serif; font-size: 16px; } iframe, img, video { max-width: 100%; } iframe { width: 100%; min-height: 400px; }',
                    font_size_formats: '8pt 10pt 12pt 14pt 16pt 18pt 24pt 36pt 48pt',
                    menubar: 'file edit view insert format tools table',
                    skin: false,
                    content_css: false,
                    license_key: 'gpl',
                    style_formats: [
                        { title: 'Paragraph', format: 'p' },
                        { title: 'Headings', items: [
                            { title: 'H1', format: 'h1' },
                            { title: 'H2', format: 'h2' },
                            { title: 'H3', format: 'h3' },
                            { title: 'H4', format: 'h4' },
                            { title: 'H5', format: 'h5' },
                            { title: 'H6', format: 'h6' },
                        ] },
                    ],
                    block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6',
                    toolbar_sticky: true,
                    promotion: false,
                    branding: false,
                    statusbar: true,
                    elementpath: false,
                    resize: true,
                    entity_encoding: 'raw',
                    setup(editor) {
                        const syncValue = () => {
                            textarea.value = editor.getContent();
                            textarea.dispatchEvent(new Event('input', { bubbles: true }));
                            textarea.dispatchEvent(new Event('change', { bubbles: true }));
                        };

                        editor.on('init', () => editor.setContent(textarea.value || ''));
                        editor.on('change input undo redo keyup blur', syncValue);

                        editor.ui.registry.addButton('addVideo', {
                            text: '+ Video',
                            onAction: () => editor.insertContent(`
                                <figure class="media-caption">
                                    <video autoplay controls loop muted width="100%"><source src="https://placehold.co/800x450" type="video/mp4"></video>
                                    <figcaption class="media-caption-text">Tulis caption video di sini.</figcaption>
                                </figure>
                            `),
                        });

                        editor.ui.registry.addButton('addImage', {
                            text: '+ Image',
                            onAction: () => editor.insertContent(`
                                <figure class="media-caption">
                                    <img alt="" data-widget="image" src="https://placehold.co/800x450" width="100%">
                                    <figcaption class="media-caption-text">Tulis caption gambar di sini.</figcaption>
                                </figure>
                            `),
                        });

                        editor.ui.registry.addButton('addBorderMerah', {
                            text: '+ Border',
                            onAction: () => editor.insertContent('<div style="border: 1px solid red; padding: 20px;">Konten</div>'),
                        });

                        editor.ui.registry.addButton('addSlider', {
                            text: '+ Slider',
                            onAction: () => editor.insertContent(`
                                <div class="tmce-slider" data-index="0">
                                    <div class="tmce-slides">
                                        <figure class="active"><img alt="" src="https://placehold.co/800x450" width="100%"><figcaption class="media-caption-text">Caption gambar pertama</figcaption></figure>
                                        <figure><img alt="" src="https://placehold.co/800x450" width="100%"><figcaption class="media-caption-text">Caption gambar kedua</figcaption></figure>
                                        <figure><img alt="" src="https://placehold.co/800x450" width="100%"><figcaption class="media-caption-text">Caption gambar ketiga</figcaption></figure>
                                    </div>
                                    <div class="tmce-controls"><button type="button" class="prev">Prev</button><button type="button" class="next">Next</button></div>
                                </div>
                            `),
                        });
                    },
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initialize, { once: true });
            } else {
                initialize();
            }
        };
    </script>
@endonce

<script>
    window.initializeEmberTinyMce(@js($id));
</script>
