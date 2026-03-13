@extends('report.layout', ['title' => $title])

@section('content')
<table>
  <thead>
    <tr>
      <th>No</th>
      <th>Tanggal</th>
      <th>BS / Petugas</th>
      <th>Status</th>
      <th>Jenis Kegiatan</th>
      <th>Rencana Biaya (Rp)</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($items as $i => $item)
      <tr>
        <td align="center">{{ $i + 1 }}</td>
        <td align="center">{{ \Carbon\Carbon::parse($item->date)->format('d/m/Y') }}</td>
        <td>{{ $item->user->name ?? '-' }} ({{ $item->user->username ?? '-' }})</td>
        <td align="center">{{ \App\Models\ActivityPlan::Statuses[$item->status] ?? $item->status }}</td>
        <td>
          @foreach ($item->details as $detail)
            {{ $detail->type->name ?? '-' }}@if(!$loop->last), @endif
          @endforeach
        </td>
        <td align="right">{{ number_format($item->total_cost, 0, ',', '.') }}</td>
      </tr>
    @empty
      <tr>
        <td colspan="6" align="center">Tidak ada data</td>
      </tr>
    @endforelse
  </tbody>
  @if ($items->count())
    <tfoot>
      <tr>
        <th colspan="5">Total</th>
        <th align="right">{{ number_format($items->sum('total_cost'), 0, ',', '.') }}</th>
      </tr>
    </tfoot>
  @endif
</table>
@endsection
