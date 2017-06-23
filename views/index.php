<?php defined('APPLICATION') or die;

$blockedUsers = $this->data('BlockedUsers');
$baseUrl = $this->data('BaseUrl');
$tk = $this->data('TransientKey');
?>

<style>
    .DataTable {
        table-layout: auto;
    }
</style>
<div class="FormTitleWrapper BlockUserPreferences">
    <h1 class="H"><?= $this->title() ?></h1>
    <div class="Info"><?= t('If you feel harassed by some user, you can minify his/her visibility to you') ?></div>
    <?= $this->Form->open(), $this->Form->errors() ?>
    <table class="DataTable BlockUserTable">
        <thead>
            <tr>
                <th colspan="2"><?= t('User') ?></th>
                <th><?= t('Actions') ?></th>
                <th><?= t('Comment') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($blockedUsers as $blockedUser): ?>
            <tr>
                <td class="BlockUserPhoto"><?= userPhoto($blockedUser) ?></td>
                <td class="BlockUserName"><?= userAnchor($blockedUser) ?></td>
                <td class="BlockUserActions">
                <?php
                    echo anchor(
                        t('Edit'),
                        $baseUrl.'edit/'.$blockedUser['Name'].'/'.$tk,
                        ['class' => 'Button Popup']
                    );
                    echo anchor(
                        t('Remove'),
                        $baseUrl.'delete/'.$blockedUser['Name'].'/'.$tk,
                        ['class' => 'Button PopConfirm']
                    );
                ?>
                </td>
                <td class="BlockUserComment">
                    <div class="P"><?= Gdn_Format::text($blockedUser['Comment']) ?></div>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <ul class="AddBlockUser">
        <li class="AddBlockUserLabel"><?= $this->Form->label('Add a new user to the list', 'User') ?></li>
        <li class="AddBlockUserTokenInput"><?= $this->Form->textBox('User', ['class' => 'MultiComplete']) ?></li>
        <li class="AddBlockUserButton">
            <?= anchor('Add', $baseUrl.'add', ['class' => 'Button Popup']) ?>
        </li>
    </ul>

    <?= $this->Form->close() ?>
</div>

<script>
jQuery(document).ready(function($) {
    var userNameInput = $( ".AddBlockUserTokenInput input" );

    $.fn.userTokenInput = function() {
        // The link to change on user input.
        var userAddLink =  $( ".AddBlockUserButton a" );

        // Author tag token input.
        var $author = $(this);

        var author = $author.val();
        if (author && author.length) {
            author = author.split(",");
            for (i = 0; i < author.length; i++) {
                author[i] = { id: i, name: author[i] };
            }
        } else {
            author = [];
        }

        $author.tokenInput(gdn.url( "/user/tagsearch" ), {
            // hintText: gdn.definition( "TagHint", "Start to type..." ),
            hintText: "",
            tokenValue: "name",
            searchingText: "", // search text gives flickery ux, don't like
            searchDelay: 300,
            minChars: 1,
            maxLength: 25,
            prePopulate: author,
            animateDropdown: false,
            tokenLimit: 1,
            onAdd: function (item) {
                // Change add user link based on choice.
                $( userAddLink ).attr(
                    "href",
                    encodeURI(
                        gdn.url( "<?= $baseUrl ?>add/" ) +
                        item.name +
                        "/" +
                        gdn.definition( "TransientKey" )
                    )
                );
            },
            onDelete: function (item) {
                $( userAddLink ).attr("href", gdn.url( "<?= $baseUrl ?>add" ));
            }
        });
    };

    // Enable multicomplete on selected inputs
    $( ".MultiComplete" ).userTokenInput();
});
</script>