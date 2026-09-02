(function (blocks, element, blockEditor, components, serverSideRender, i18n) {
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var ToggleControl = components.ToggleControl;
    var RangeControl = components.RangeControl;
    var ServerSideRender = serverSideRender;
    var __ = i18n.__;

    // Helper to get label
    var getLabel = function (key, defaultText) {
        if (typeof sppGa4RankData !== 'undefined' && sppGa4RankData.labels && sppGa4RankData.labels[key]) {
            return sppGa4RankData.labels[key];
        }
        // Fallback to i18n if JSONs eventually exist
        return __(defaultText, 'simple-popular-posts-for-ga4');
    };

    var useBlockProps = blockEditor.useBlockProps;

    registerBlockType('simple-popular-posts-for-ga4/ranking', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            return el(
                'div',
                blockProps,
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: getLabel('settings', 'Settings'), initialOpen: true },
                        el(TextControl, {
                            label: getLabel('title', 'Title'),
                            value: attributes.title,
                            onChange: function (val) { setAttributes({ title: val }); }
                        }),
                        el(SelectControl, {
                            label: getLabel('title_tag', 'Title Tag'),
                            value: attributes.title_tag || 'h2',
                            options: [
                                { label: 'H2', value: 'h2' },
                                { label: 'H3', value: 'h3' },
                                { label: 'H4', value: 'h4' },
                                { label: 'span', value: 'span' },
                                { label: getLabel('title_none', 'No Title Output'), value: 'none' }
                            ],
                            onChange: function (val) { setAttributes({ title_tag: val }); }
                        }),
                        el(TextControl, {
                            label: getLabel('limit', 'Limit'),
                            type: 'number',
                            value: attributes.limit,
                            min: 1,
                            max: 20,
                            onChange: function (val) { setAttributes({ limit: parseInt(val) }); }
                        }),
                        el(SelectControl, {
                            label: getLabel('design_preset', 'Design Preset'),
                            value: attributes.style_preset || 'list',
                            options: [
                                { label: getLabel('preset_list', 'Default (List)'), value: 'list' },
                                { label: getLabel('preset_numbered', 'Numbered List'), value: 'numbered' },
                                { label: getLabel('preset_card', 'Card Style'), value: 'card' }
                            ],
                            onChange: function (val) { setAttributes({ style_preset: val }); }
                        }),
                        attributes.style_preset === 'card' && el('p', { style: { fontSize: '12px', color: '#666', marginTop: '-10px', marginBottom: '15px' } },
                            getLabel('card_style_help', 'Note: In Card Style, images are forced to 16:9 ratio. Set Thumbnail Size above to control resolution.')
                        ),
                        attributes.style_preset === 'card' && el(TextControl, {
                            label: getLabel('card_min_width', 'Card Min Width (px)'),
                            type: 'number',
                            value: attributes.card_min_width || 150,
                            help: getLabel('card_min_width_help', 'Minimum width of each card. Cards will expand to fill the available space.'),
                            onChange: function (val) { setAttributes({ card_min_width: parseInt(val) }); }
                        }),
                        el(SelectControl, {
                            label: getLabel('time_range', 'Time Range'),
                            value: attributes.range,
                            options: [
                                { label: getLabel('range_7d', '7 Days'), value: '7d' },
                                { label: getLabel('range_30d', '30 Days'), value: '30d' }
                            ],
                            onChange: function (val) { setAttributes({ range: val }); }
                        }),
                        el(TextControl, {
                            label: getLabel('cat_id', 'Category ID'),
                            value: attributes.cat_id,
                            help: getLabel('cat_id_help', 'Default includes sub-categories. (Single ID only)'),
                            onChange: function (val) { setAttributes({ cat_id: val }); }
                        }),
                        el(TextControl, {
                            label: getLabel('tag_id', 'Tag ID'),
                            value: attributes.tag_id,
                            help: getLabel('tag_id_help', '(Single ID only)'),
                            onChange: function (val) { setAttributes({ tag_id: val }); }
                        }),
                        el(TextControl, {
                            label: getLabel('exclude_ids', 'Exclude Post IDs (comma-separated)'),
                            value: attributes.exclude_ids,
                            help: getLabel('exclude_help', 'e.g. 123, 456 (Max 100 IDs)'),
                            onChange: function (val) { setAttributes({ exclude_ids: val }); }
                        }),
                        el(TextControl, {
                            label: getLabel('filter_days', 'Filter by Publish Date (days)'),
                            type: 'number',
                            min: 0,
                            value: attributes.filter_days,
                            help: getLabel('filter_help', '0 for all time. e.g. 365 for 1 year.'),
                            onChange: function (val) { setAttributes({ filter_days: parseInt(val) }); }
                        }),
                        el(TextControl, {
                            label: getLabel('shorten_title', 'Shorten Title (chars)'),
                            type: 'number',
                            min: 0,
                            value: attributes.shorten_title,
                            help: getLabel('shorten_help', 'Truncate the post title to this number of characters. 0 to disable.'),
                            onChange: function (val) { setAttributes({ shorten_title: parseInt(val) }); }
                        }),
                        el(ToggleControl, {
                            label: getLabel('prevent_duplicates', 'Prevent duplicates on the same page'),
                            checked: attributes.prevent_duplicates,
                            onChange: function (val) { setAttributes({ prevent_duplicates: val }); }
                        }),
                        el(ToggleControl, {
                            label: getLabel('show_thumb', 'Show Thumbnail'),
                            checked: attributes.show_thumb,
                            onChange: function (val) { setAttributes({ show_thumb: val }); }
                        }),
                        el('div', { style: { display: attributes.show_thumb ? 'block' : 'none', marginLeft: '20px', borderLeft: '2px solid #ddd', paddingLeft: '10px' } },
                            el(TextControl, {
                                label: getLabel('thumb_w', 'Thumbnail Width (px)'),
                                type: 'number',
                                value: attributes.thumb_w,
                                onChange: function (val) { setAttributes({ thumb_w: parseInt(val) }); }
                            }),
                            el(TextControl, {
                                label: getLabel('thumb_h', 'Thumbnail Height (px)'),
                                type: 'number',
                                value: attributes.thumb_h,
                                onChange: function (val) { setAttributes({ thumb_h: parseInt(val) }); }
                            })
                        ),
                        el(ToggleControl, {
                            label: getLabel('show_date', 'Show Date'),
                            checked: attributes.show_date,
                            onChange: function (val) { setAttributes({ show_date: val }); }
                        }),
                        el('div', { style: { display: attributes.show_date ? 'block' : 'none', marginLeft: '20px', borderLeft: '2px solid #ddd', paddingLeft: '10px', marginBottom: '15px' } },
                            el(SelectControl, {
                                label: getLabel('date_format', 'Date Format'),
                                value: attributes.date_format || 'wp_default',
                                options: [
                                    { label: getLabel('fmt_wp_default', 'WordPress Default'), value: 'wp_default' },
                                    { label: 'Y/m/d', value: 'Y/m/d' },
                                    { label: 'Y-m-d', value: 'Y-m-d' },
                                    { label: 'd/m/Y', value: 'd/m/Y' },
                                    { label: 'F j, Y', value: 'F j, Y' }
                                ],
                                onChange: function (val) { setAttributes({ date_format: val }); }
                            })
                        )
                    )
                ),
                el(
                    ServerSideRender,
                    {
                        block: 'simple-popular-posts-for-ga4/ranking',
                        attributes: attributes
                    }
                )
            );
        },
        save: function () {
            return null; // Rendered on server
        }
    });
}(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.serverSideRender,
    window.wp.i18n
));
