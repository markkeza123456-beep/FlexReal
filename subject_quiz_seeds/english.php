<?php
return function (int $lessonNo): array {
    return [
        ["Lesson {$lessonNo}: Choose the correct sentence.", 'She go to school every day.', 'She goes to school every day.', 'She going to school every day.', 'She gone to school every day.', 'B'],
        ["Lesson {$lessonNo}: Synonym of \"happy\" is ...", 'Sad', 'Angry', 'Joyful', 'Tired', 'C'],
        ["Lesson {$lessonNo}: I ___ a student.", 'am', 'is', 'are', 'be', 'A'],
        ["Lesson {$lessonNo}: Which one is a noun?", 'Run', 'Beautiful', 'Teacher', 'Quickly', 'C'],
        ["Lesson {$lessonNo}: Past tense of \"eat\" is ...", 'eated', 'ate', 'eatening', 'eat', 'B'],
    ];
};
