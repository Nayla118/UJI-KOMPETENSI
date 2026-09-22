namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;

class ItemController extends Controller
{
    public function destroy(Request $request)
    {
        $ids = $request->input('ids', []);
        Item::whereIn('id', $ids)->delete();
        return redirect()->route('items.index')->with('success', 'Selected items deleted successfully.');
    }

    public function destroyAll()
    {
        Item::truncate();
        return redirect()->route('items.index')->with('success', 'All items deleted successfully.');
    }
}
