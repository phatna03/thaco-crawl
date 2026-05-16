<style>
    /*
     * Chỉ /admin/posts, viewport < lg — không ảnh hưởng desktop bố cục bảng chuẩn.
     * Nút bật/tắt cột đã tắt trong PostResource (không còn cột toggleable).
     */
    @media (max-width: 1023px) {
        .fi-resource-posts.fi-resource-list-records-page .fi-ta-table thead {
            display: none;
        }

        .fi-resource-posts.fi-resource-list-records-page
            .fi-ta-table
            tbody
            tr.fi-ta-row
            > td.hidden {
            display: none !important;
        }

        .fi-resource-posts.fi-resource-list-records-page
            .fi-ta-table
            tbody
            tr.fi-ta-row
            > td:not(.hidden) {
            display: block;
            width: 100% !important;
            box-sizing: border-box;
            border-bottom: 1px solid rgb(229 231 235 / 0.55);
        }

        .dark
            .fi-resource-posts.fi-resource-list-records-page
            .fi-ta-table
            tbody
            tr.fi-ta-row
            > td:not(.hidden) {
            border-bottom-color: rgb(255 255 255 / 0.08);
        }

        .fi-resource-posts.fi-resource-list-records-page
            .fi-ta-table
            tbody
            tr.fi-ta-row
            > td:not(.hidden):last-child {
            border-bottom: 0;
        }

        .fi-resource-posts.fi-resource-list-records-page .fi-ta-table tbody tr.fi-ta-row {
            display: block;
            margin-bottom: 0.75rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            border: 1px solid rgb(229 231 235 / 1);
            background-color: rgb(255 255 255 / 1);
        }

        .dark .fi-resource-posts.fi-resource-list-records-page .fi-ta-table tbody tr.fi-ta-row {
            border-color: rgb(255 255 255 / 0.1);
            background-color: rgb(17 24 39 / 1);
        }

        .fi-resource-posts.fi-resource-list-records-page
            .fi-ta-table
            tbody
            tr.fi-ta-row
            > td:not(.hidden)
            .fi-ta-actions {
            justify-content: flex-end;
            width: 100%;
        }

        /* Checkbox chọn dòng: gọn đầu thẻ, không chiếm full width kiểu ô riêng */
        .fi-resource-posts.fi-resource-list-records-page
            .fi-ta-table
            tbody
            tr.fi-ta-row
            > td:not(.hidden):first-child
            .fi-ta-record-checkbox {
            margin-top: 0;
            margin-bottom: 0;
        }
    }
</style>
