@extends('admin.layout')

@section('content')
    <h1>Items</h1>
    <form action="{{ route('items.destroy') }}" method="POST">
        @csrf
        @method('DELETE')
        <table class="table">
            <thead>
                <tr>
                    <th>Select All <input type="checkbox" id="select-all"></th>
                    <th>Name</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="{{ $item->id }}"></td>
                        <td>{{ $item->name }}</td>
                        <td><button type="submit" class="btn btn-danger">Delete</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div>
            <button type="submit" class="btn btn-danger">Delete Selected</button>
            <a href="{{ route('items.destroy-all') }}" class="btn btn-danger">Delete All</a>
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
