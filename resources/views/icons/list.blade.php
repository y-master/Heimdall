@extends('layouts.app')

@section('content')
        <section class="module-container">
            @if($enable_auth_admin_controls)
            <header>
                <div class="section-title">
                    {{ __('app.icons.library') }}
                </div>
                <div class="module-actions">
                    <a href="{{ route('settings.index', []) }}" class="button"><i class="fa fa-ban"></i><span>{{ __('app.buttons.cancel') }}</span></a>
                </div>
            </header>

            <form method="POST" action="{{ route('icons.store') }}" enctype="multipart/form-data" style="padding: 10px 20px;">
                {!! csrf_field() !!}
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="text" name="name" placeholder="{{ __('app.icons.icon_name') }}" class="form-control" style="max-width: 240px;" />
                    <input type="file" name="file" required class="form-control" />
                    <button type="submit" class="button"><i class="fa fa-plus"></i><span>{{ __('app.buttons.add') }}</span></button>
                </div>
            </form>

            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="width: 60px;">{{ __('app.apps.icon') }}</th>
                        <th>{{ __('app.title') }}</th>
                        <th style="width: 100px;">{{ __('app.icons.size') }}</th>
                        <th style="width: 110px;">{{ __('app.icons.added') }}</th>
                        <th class="text-center" style="width: 120px;">{{ __('app.settings.edit') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if($icons->first())
                        @foreach($icons as $icon)
                            @php
                                $usedBy = $usage[$icon->path] ?? 0;
                            @endphp
                            <tr>
                                <td><img src="{{ $icon->url() }}" alt="{{ $icon->name }}" style="max-width: 32px; max-height: 32px;" /></td>
                                <td>
                                    {{ html()->form('PATCH', route('icons.update', $icon->id))->style('display:flex; gap:5px; align-items:center;')->open() }}
                                        <input type="text" name="name" value="{{ $icon->name }}" class="form-control" style="max-width: 220px;" />
                                        <button type="submit" class="link" title="{{ __('app.settings.edit') }}"><i class="fas fa-save"></i></button>
                                    {{ html()->form()->close() }}
                                </td>
                                <td>{{ format_bytes($icon->size ?? 0) }}</td>
                                <td>{{ $icon->created_at?->format('Y-m-d') }}</td>
                                <td class="text-center">
                                    {{ html()->form('POST', route('icons.replace', $icon->id))->attribute('enctype', 'multipart/form-data')->open() }}
                                        {!! csrf_field() !!}
                                        <input type="file" name="file" id="replace-file-{{ $icon->id }}" style="display: none;" accept="image/*" data-form-id="replace-form-{{ $icon->id }}" />
                                    {{ html()->form()->close() }}
                                    <button class="link" title="{{ __('app.icons.replace') }}" onclick="document.getElementById('replace-file-{{ $icon->id }}').click();"><i class="fas fa-sync-alt"></i></button>
                                    @if($usedBy > 0)
                                        <span title="{{ __('app.icons.in_use', ['count' => $usedBy]) }}" style="opacity: .35; cursor: not-allowed;"><i class="fa fa-trash-alt"></i></span>
                                    @else
                                        {{ html()->form('DELETE', route('icons.destroy', $icon->id))->style('display:inline')->open() }}
                                            <button class="link" type="submit" title="{{ __('app.delete') }}" onclick="return confirm('{{ __('app.icons.confirm_delete') }}')"><i class="fa fa-trash-alt"></i></button>
                                        {{ html()->form()->close() }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="5" class="form-error text-center">
                                <strong>{{ __('app.icons.no_icons') }}</strong>
                            </td>
                        </tr>
                    @endif


                </tbody>
            </table>
            @else
            <header>
                <div class="section-title">
                    {{ __('app.unauthorized_for_form') }}
                </div>
            </header>
            @endif
        </section>


@endsection

@section('scripts')
<script>
    document.querySelectorAll('input[type="file"][data-form-id]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (this.files.length) {
                document.getElementById(this.getAttribute('data-form-id')).submit();
            }
        });
    });
</script>
@endsection
