function updateNewsOptionsListener(changed, collection, shortcode) {

    function attributeByName(name) {
        return _.find(
            collection,
            function (viewModel) {
                return name === viewModel.model.get('attr');
            }
        );
    }

    var updatedVal = changed.value,
        category = attributeByName('site_category_slug'),
        count = attributeByName('count');
        offset = attributeByName('offset');
        postid = attributeByName('id');

    if (updatedVal == 'full') {
        postid.$el.show();
        offset.$el.hide();
        category.$el.hide();
        count.$el.hide();
    } else if (updatedVal != 'full')  {
        postid.$el.hide();
        offset.$el.show();
        category.$el.show();
        count.$el.show();
    } else {
        postid.$el.hide();
        offset.$el.hide();
        category.$el.hide();
        count.$el.hide();
    }
}
wp.shortcake.hooks.addAction('uamswp_news.output', updateNewsOptionsListener);