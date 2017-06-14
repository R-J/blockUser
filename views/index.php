<?php defined('APPLICATION') or die ?>
<style>
</style>
<div class="FormTitleWrapper">
    <h1 class="H"><?= $this->title() ?></h1>
    <div class="Info"><?= t('If you feel harassed by some user, you can minify his/her visibility to you') ?></div>
    <?= $this->Form->open(), $this->Form->errors() ?>

<?php
decho($this->data('BlockedUsers'));
?>
    <table>
        <thead>
            <tr>
                <th><?= t('User Name') ?></th>
                <th>&nbsp;</th>
                <th>&nbsp;</th>
                <th>&nbsp;</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($this->data('BlockedUsers') as $blockedUser): ?>
            <tr>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <ul class="AddBlockUser">
        <li class="AddBlockUserLabel"><?= $this->Form->label('Add a new user to the list', 'User') ?></li>
        <li class="AddBlockUserTokenInput"><?= $this->Form->textBox('User', ['class' => 'MultiComplete']) ?></li>
        <li class="AddBlockUserButton">
            <?= anchor('Add', '/profile/blockuser/add', ['class' => 'Button Popup']) ?>
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
                        gdn.url( "/profile/blockuser/add/" ) +
                        item.name +
                        "/" +
                        gdn.definition( "TransientKey" )
                    )
                );
            },
            onDelete: function (item) {
                $( userAddLink ).attr("href", gdn.url( "/plugin/blockuser/add" ));
            }
        });
    };

    // Enable multicomplete on selected inputs
    $( ".MultiComplete" ).userTokenInput();
});
</script>