<?php

declare(strict_types=1);

use App\Controllers\AdminAttemptController as Attempts;
use App\Controllers\AdminMockController as Mocks;
use App\Controllers\AdminSecurityController as SecurityCtl;
use App\Controllers\AdminSettingsController as SettingsCtl;
use App\Controllers\AdminUserController as Users;
use App\Controllers\AuthController as AuthCtl;
use App\Controllers\ExamController as Exam;
use App\Controllers\ExpertController as Expert;
use App\Controllers\StudentController as Student;
use App\Router;

return static function (Router $r): void {
    // Kirish
    $r->get('/auth/me', [AuthCtl::class, 'me']);
    $r->post('/auth/login', [AuthCtl::class, 'login']);
    $r->post('/auth/register', [AuthCtl::class, 'register']);
    $r->post('/auth/logout', [AuthCtl::class, 'logout']);
    $r->post('/auth/password', [AuthCtl::class, 'changePassword']);

    // O'quvchi
    $r->get('/student/mocks', [Student::class, 'mocks']);
    $r->post('/student/mocks/{id}/start', [Student::class, 'start']);
    $r->post('/student/random', [Student::class, 'random']);
    $r->get('/student/attempts', [Student::class, 'attempts']);
    $r->get('/student/attempts/{id}/result', [Student::class, 'result']);

    // Imtihon jarayoni
    $r->post('/exam/{id}/claim', [Exam::class, 'claim']);
    $r->get('/exam/{id}/state', [Exam::class, 'state']);
    $r->post('/exam/{id}/section/start', [Exam::class, 'sectionStart']);
    $r->post('/exam/{id}/section/finish', [Exam::class, 'sectionFinish']);
    $r->post('/exam/{id}/sync', [Exam::class, 'sync']);
    $r->get('/exam/{id}/audio/{asset}', [Exam::class, 'audio']);
    $r->get('/exam/{id}/speaking/asset/{asset}', [Exam::class, 'speakingAsset']);
    $r->post('/exam/{id}/speaking/start', [Exam::class, 'speakingStart']);
    $r->post('/exam/{id}/speaking/next', [Exam::class, 'speakingNext']);
    $r->post('/exam/{id}/speaking/upload', [Exam::class, 'speakingUpload']);
    $r->post('/exam/{id}/speaking/finish', [Exam::class, 'speakingFinish']);

    // Administrator: mocklar
    $r->get('/admin/dashboard', [Attempts::class, 'dashboard']);
    $r->get('/admin/mocks', [Mocks::class, 'list']);
    $r->post('/admin/mocks', [Mocks::class, 'create']);
    $r->get('/admin/mocks/{id}', [Mocks::class, 'get']);
    $r->put('/admin/mocks/{id}', [Mocks::class, 'update']);
    $r->delete('/admin/mocks/{id}', [Mocks::class, 'delete']);
    $r->post('/admin/mocks/{id}/validate', [Mocks::class, 'validate']);
    $r->post('/admin/mocks/{id}/status', [Mocks::class, 'status']);
    $r->post('/admin/mocks/{id}/duplicate', [Mocks::class, 'duplicate']);
    $r->post('/admin/mocks/{id}/assets', [Mocks::class, 'uploadAsset']);
    $r->get('/admin/mocks/{id}/attempts', [Mocks::class, 'attempts']);
    $r->get('/admin/mocks/{id}/export', [Mocks::class, 'exportCsv']);
    $r->post('/admin/mocks/{id}/rescore', [Mocks::class, 'rescore']);
    $r->post('/admin/mocks/{id}/rasch', [Mocks::class, 'rasch']);
    $r->post('/admin/mocks/{id}/rasch/clear', [Mocks::class, 'raschClear']);
    $r->get('/admin/mocks/{id}/items', [Mocks::class, 'items']);
    $r->post('/admin/mocks/{id}/items/accept', [Mocks::class, 'acceptAlternative']);
    $r->post('/admin/mocks/{id}/publish', [Mocks::class, 'publish']);
    $r->get('/admin/assets/{asset}', [Mocks::class, 'asset']);
    $r->post('/admin/assets/{asset}/duration', [Mocks::class, 'setAssetDuration']);
    $r->delete('/admin/assets/{asset}', [Mocks::class, 'deleteAsset']);
    $r->get('/admin/grading', [Mocks::class, 'gradingOverview']);

    // Administrator: urinishlar
    $r->get('/admin/live', [Attempts::class, 'live']);
    $r->get('/admin/attempts/{id}', [Attempts::class, 'detail']);
    $r->post('/admin/attempts/{id}/reset', [Attempts::class, 'reset']);
    $r->post('/admin/attempts/{id}/terminate', [Attempts::class, 'terminate']);
    $r->get('/speaking/{id}', [Attempts::class, 'speakingAudio']);

    // Administrator: foydalanuvchilar va sozlamalar
    $r->get('/admin/users', [Users::class, 'list']);
    $r->post('/admin/users', [Users::class, 'create']);
    $r->post('/admin/users/bulk', [Users::class, 'bulk']);
    $r->put('/admin/users/{id}', [Users::class, 'update']);
    $r->post('/admin/users/{id}/password', [Users::class, 'resetPassword']);
    $r->get('/admin/users/{id}/attempts', [Users::class, 'attempts']);
    $r->get('/admin/settings', [SettingsCtl::class, 'get']);
    $r->put('/admin/settings', [SettingsCtl::class, 'update']);
    $r->get('/admin/security/throttle', [SecurityCtl::class, 'throttle']);
    $r->post('/admin/security/throttle/clear', [SecurityCtl::class, 'clear']);

    // Ekspert
    $r->get('/expert/queue', [Expert::class, 'queue']);
    $r->post('/expert/next', [Expert::class, 'next']);
    $r->post('/expert/rate', [Expert::class, 'rate']);
    $r->post('/expert/release', [Expert::class, 'release']);
    $r->get('/expert/history', [Expert::class, 'history']);
    $r->get('/expert/image/{attempt}/{asset}', [Expert::class, 'image']);
};
