@extends('report.layout', ['title' => $title])

@section('content')
<table>
  <thead>
    <tr>
      <th>No</th>
      <th>Tanggal</th>
      <th>BS / Petugas</th>
      <th>Jenis Kegiatan</th>
      <th>Produk</th>
      <th>Lokasi</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($items as $i => $item)
      <tr>
        <td align="center">{{ $i + 1 }}</td>
        <td align="center">{{ \Carbon\Carbon::parse($item->date)->format('d/m/Y') }}</td>
        <td>{{ $item->user->name ?? '-' }}</td>
        <td>{{ $item->type->name ?? '-' }}</td>
        <td>{{ $item->product->name ?? '-' }}</td>
        <td>{{ $item->location ?? '-' }}</td>
        <td align="center">{{ ucfirst(str_replace('_', ' ', $item->status)) }}</td>
      </tr>
    @empty
      <tr>
        <td colspan="7" align="center">Tidak ada data</td>
      </tr>
    @endforelse
  </tbody>
  @if ($items->count())
    <tfoot>
      <tr>
        <th colspan="7">Total: {{ $items->count() }} kegiatan</th>
      </tr>
    </tfoot>
  @endif
</table>
@endsection
