<?php defined('APPLICATION') or die ?>
<div class="FormTitleWrapper">
    <h1 class="H"><?= htmlEsc($this->title()) ?></h1>
    <?= $this->Form->open(), $this->Form->errors() ?>
    <table>
        <thead>
        </thead>
        <tbody>
            <tr>
                <td><?= t('Block Private Messages') ?></td>
                <td><?= $this->Form->checkBox('BlockPrivateMessages') ?></td>
                <td rowspan="6" class="BlockUserEdit">
                    <?= userPhoto($this->data('BlockedUser')) ?>
                    <?= userAnchor($this->data('BlockedUser')) ?>
                </td>
            </tr>
            <tr>
                <td><?= t('Block Notifications') ?></td>
                <td><?= $this->Form->checkBox('BlockNotifications') ?></td>
            </tr>
            <tr>
                <td><?= t('Block Discussions') ?></td>
                <td><?= $this->Form->checkBox('BlockDiscussions') ?></td>
            </tr>
            <tr>
                <td><?= t('Block Comments') ?></td>
                <td><?= $this->Form->checkBox('BlockComments') ?></td>
            </tr>
            <tr>
                <td><?= t('Block Activities') ?></td>
                <td><?= $this->Form->checkBox('BlockActivities') ?></td>
            </tr>
            <tr>
                <td><?= t('Disallow writing on my wall') ?></td>
                <td><?= $this->Form->checkBox('DisallowWallPosts') ?></td>
            </tr>
        </tbody>
    </table>
    <ul>
        <li><?= $this->Form->label('You can save an additional comment here', 'Comment') ?></li>
        <li><?= $this->Form->textBox('Comment', ['MultiLine' => true]) ?></li>
    </ul>
    <div class="Buttons Buttons-Confirm">
       <?= $this->Form->button('Save', ['class' => 'Button Primary']) ?>
       <?= $this->Form->button('Cancel',['type' => 'button', 'class' => 'Button Close']) ?>
    </div>
    <?= $this->Form->close() ?>
</div>

