(function($) {
    'use strict';

    // 文档加载完成
    $(document).ready(function() {
        initRefreshModels();
    });

    // 刷新模型列表
    function initRefreshModels() {
        $('#wp-slug-translate-refresh-models').on('click', function() {
            var button = $(this);
            var statusSpan = $('#wp-slug-translate-models-status');

            button.prop('disabled', true);
            button.find('.dashicons').addClass('dashicons-update-alt');
            button.find('.dashicons').css('animation', 'spin 1s linear infinite');
            statusSpan.text('正在刷新模型列表...').css('color', '#666');

            $.ajax({
                url: wp_slug_translate_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'wp_slug_translate_refresh_models',
                    nonce: wp_slug_translate_data.nonce
                },
                success: function(response) {
                    button.prop('disabled', false);
                    button.find('.dashicons').css('animation', '');

                    if (response.success) {
                        statusSpan.text('刷新成功！共 ' + response.data.total + ' 个模型').css('color', 'green');

                        // 更新模型计数
                        $('#wp-slug-translate-models-count').text(response.data.total);

                        // 保存当前选中的模型
                        var currentModel = $('#wp-slug-translate-model').val();

                        // 更新模型选项
                        var modelSelect = $('#wp-slug-translate-model');
                        modelSelect.empty();
                        var selectedExists = false;

                        $.each(response.data.models, function(modelKey, modelName) {
                            modelSelect.append('<option value="' + modelKey + '">' + modelName + '</option>');
                            if (modelKey === currentModel) {
                                selectedExists = true;
                            }
                        });

                        // 如果之前选择的模型仍然存在，保持选中状态
                        if (selectedExists) {
                            modelSelect.val(currentModel);
                        }

                        // 3秒后清除状态信息
                        setTimeout(function() {
                            statusSpan.text('最后更新：' + new Date().toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' })).css('color', '#999');

                            // 5分钟后清除
                            setTimeout(function() {
                                statusSpan.text('');
                            }, 300000);
                        }, 3000);
                    } else {
                        statusSpan.text('刷新失败：' + response.data.message).css('color', 'red');
                    }
                },
                error: function() {
                    button.prop('disabled', false);
                    button.find('.dashicons').css('animation', '');
                    statusSpan.text('网络错误，请稍后重试').css('color', 'red');
                }
            });
        });
    }
})(jQuery);

// 添加旋转动画
$(function() {
    $("<style>")
        .prop("type", "text/css")
        .html("\
            @keyframes spin {\
                from { transform: rotate(0deg); }\
                to { transform: rotate(360deg); }\
            }\
        ")
        .appendTo("head");
});
