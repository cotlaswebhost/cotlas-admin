( function () {
        var addFilter  = wp.hooks.addFilter;
        var el         = wp.element.createElement;
        var __         = wp.i18n.__;
        var registerPlugin              = wp.plugins.registerPlugin;
        var PluginDocumentSettingPanel  = wp.editPost.PluginDocumentSettingPanel;
        var useSelect   = wp.data.useSelect;
        var useDispatch = wp.data.useDispatch;
        var ToggleControl = wp.components.ToggleControl;

        /* ------------------------------------------------------------------ */
        /* 1a. Featured Posts parameter in the GenerateBlocks Query block       */
        /* ------------------------------------------------------------------ */
        var FEATURED_PARAM = {
                id: 'featuredPosts',
                type: 'select',
                default: 'only',
                selectOptions: [
                        { value: 'only',    label: __( 'Only',    'gp-child' ) },
                        { value: 'exclude', label: __( 'Exclude', 'gp-child' ) },
                ],
                label:       __( 'Featured posts', 'gp-child' ),
                description: __( 'Configure how featured posts should show in the query.', 'gp-child' ),
                group: __( 'Post', 'gp-child' ),
        };

        /* ------------------------------------------------------------------ */
        /* 1b. Popular Posts parameter (order by view count, highest first)    */
        /* ------------------------------------------------------------------ */
        var POPULAR_PARAM = {
                id: 'popularPosts',
                type: 'select',
                default: 'true',
                selectOptions: [
                        { value: 'true', label: __( 'Yes', 'gp-child' ) },
                ],
                label:       __( 'Popular posts', 'gp-child' ),
                description: __( 'Order posts by total view count, highest first. Requires Post Views Counter plugin.', 'gp-child' ),
                group: __( 'Post', 'gp-child' ),
        };

        /* ------------------------------------------------------------------ */
        /* 1c. Reading List parameter (show only the visitor's bookmarked posts)*/
        /* ------------------------------------------------------------------ */
        var READING_LIST_PARAM = {
                id: 'readingListPosts',
                type: 'select',
                default: 'true',
                selectOptions: [
                        { value: 'true', label: __( 'Yes', 'gp-child' ) },
                ],
                label:       __( 'Reading list posts', 'gp-child' ),
                description: __( 'Show only posts the current visitor has bookmarked. Guests: cookie. Logged-in: database.', 'gp-child' ),
                group: __( 'Post', 'gp-child' ),
        };

        /* ------------------------------------------------------------------ */
        /* 1d. Wishlist parameter (show only the visitor's wishlisted posts)   */
        /* ------------------------------------------------------------------ */
        var WISHLIST_PARAM = {
                id: 'wishlistPosts',
                type: 'select',
                default: 'true',
                selectOptions: [
                        { value: 'true', label: __( 'Yes', 'gp-child' ) },
                ],
                label:       __( 'Wishlist posts', 'gp-child' ),
                description: __( 'Show only posts the current visitor has wishlisted. Guests: cookie. Logged-in: database.', 'gp-child' ),
                group: __( 'Post', 'gp-child' ),
        };

        /* ------------------------------------------------------------------ */
        /* 1e. My Posts parameter (current logged-in user's own posts only)   */
        /* ------------------------------------------------------------------ */
        var MY_POSTS_PARAM = {
                id: 'myPosts',
                type: 'select',
                default: 'true',
                selectOptions: [
                        { value: 'true', label: __( 'Yes', 'gp-child' ) },
                ],
                label:       __( 'My posts', 'gp-child' ),
                description: __( 'Show only published posts authored by the currently logged-in user. Logged-out visitors see no results.', 'gp-child' ),
                group: __( 'Post', 'gp-child' ),
        };

        /* Register all five params on the newer "Query" block */
        addFilter(
                'generateblocks.editor.query.query-parameters',
                'gp-child/custom-query-params',
                function ( params ) {
                        return params.concat( [ FEATURED_PARAM, POPULAR_PARAM, READING_LIST_PARAM, WISHLIST_PARAM, MY_POSTS_PARAM ] );
                }
        );

        /* Register all five params on the legacy "Query Loop" block */
        addFilter(
                'generateblocks.editor.query-loop.query-parameters',
                'gp-child/custom-query-loop-params',
                function ( params ) {
                        return params.concat( [ FEATURED_PARAM, POPULAR_PARAM, READING_LIST_PARAM, WISHLIST_PARAM, MY_POSTS_PARAM ] );
                }
        );

        /* ------------------------------------------------------------------ */
        /* 2. Block-editor Document Settings panel (right sidebar toggle)      */
        /* ------------------------------------------------------------------ */
        registerPlugin( 'gpc-featured-post-panel', {
                render: function () {
                        var postType = useSelect( function ( select ) {
                                return select( 'core/editor' ).getCurrentPostType();
                        } );

                        if ( postType !== 'post' ) {
                                return null;
                        }

                        var meta     = useSelect( function ( select ) {
                                return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
                        } );

                        var editPost   = useDispatch( 'core/editor' ).editPost;
                        var isFeatured = !! meta._is_featured;

                        return el(
                                PluginDocumentSettingPanel,
                                {
                                        name:        'gpc-featured-post-panel',
                                        title:       __( 'Featured Post', 'gp-child' ),
                                        icon:        'star-filled',
                                        initialOpen: true,
                                        className:   'gpc-featured-post-panel',
                                },
                                el( ToggleControl, {
                                        label:    __( 'Mark as Featured', 'gp-child' ),
                                        help:     isFeatured
                                                        ? __( 'This post is featured.', 'gp-child' )
                                                        : __( 'Enable to mark this post as featured.', 'gp-child' ),
                                        checked:  isFeatured,
                                        onChange: function ( value ) {
                                                editPost( { meta: { _is_featured: value } } );
                                        },
                                } )
                        );
                },
        } );
} )();
