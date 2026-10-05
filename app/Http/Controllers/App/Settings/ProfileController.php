<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Settings\ProfileUpdateRequest;
use App\Http\Requests\App\Settings\UploadPhotoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/profile/Profile');
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        session()->flash('flash.banner', __('settings.flash.profile_updated'));
        session()->flash('flash.bannerStyle', 'success');

        return to_route('app.profile.edit');
    }

    public function uploadPhoto(UploadPhotoRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->clearMediaCollection('avatar');
        $user->addMedia($request->file('photo'), 'avatar');
        $user->unsetRelation('media');

        session()->flash('flash.banner', __('settings.flash.photo_updated'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function deletePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->clearMediaCollection('avatar');
        $user->unsetRelation('media');

        session()->flash('flash.banner', __('settings.flash.photo_deleted'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }
}
