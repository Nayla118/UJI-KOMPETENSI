@extends('admin.layout')

@section('content')
    <h1>Tour Packages</h1>
    <form action="{{ route('tour-packages.destroyMultiple') }}" method="POST" id="delete-form">
        @csrf
        <table class="table">
            <thead>
                <tr>
                    <th>Select All <input type="checkbox" id="select-all"></th>
                    <th>Name</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tourPackages as $tourPackage)
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="{{ $tourPackage->id }}"></td>
                        <td>{{ $tourPackage->name }}</td>
                        <td><button type="submit" class="btn btn-danger">Delete</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div>
            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete the selected items?')">Delete Selected</button>
        </div>
    </form>

    <script>
        document.getElementById('select-all').addEventListener('change', function() {
            var checkboxes = document.querySelectorAll('input[name="ids[]"]');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = this.checked;
            }
        });
    </script>
@endsection
